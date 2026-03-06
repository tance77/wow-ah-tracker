<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\Storage;

class ExtractRealmListingsAction
{
    /**
     * Stream through a realm auction JSON file and extract listings for the given item IDs.
     *
     * Uses brace-depth counting to handle nested item objects (bonus_list, modifiers)
     * that the commodity regex cannot match. Skips bid-only auctions (buyout = 0).
     *
     * @param  string  $storageKey  Storage key for the downloaded realm auction JSON file
     * @param  int[]   $itemIds     Blizzard item IDs to extract
     * @return array<int, array<array{unit_price: int, quantity: int}>>  Listings grouped by item ID
     */
    public function __invoke(string $storageKey, array $itemIds): array
    {
        $handle = Storage::readStream($storageKey);
        $catalogSet = array_flip($itemIds);
        $grouped = [];
        $buffer = '';
        $braceDepth = 0;
        $objectStart = -1;
        // Auction objects live inside {"auctions":[{...},{...}]}, so they
        // start at brace depth 1 (the root object is depth 0->1 on its opening brace).
        // We capture objects that open at depth 2 and close back to depth 1.
        $captureDepth = 1;

        while (! feof($handle)) {
            $chunk = fread($handle, 131072); // 128KB chunks
            $buffer .= $chunk;

            $len = strlen($buffer);
            $i = 0;

            while ($i < $len) {
                $char = $buffer[$i];

                if ($char === '{') {
                    $braceDepth++;
                    if ($braceDepth === $captureDepth + 1 && $objectStart < 0) {
                        $objectStart = $i;
                    }
                } elseif ($char === '}') {
                    $braceDepth--;

                    if ($braceDepth === $captureDepth && $objectStart >= 0) {
                        $objectJson = substr($buffer, $objectStart, $i - $objectStart + 1);
                        $obj = json_decode($objectJson, true);

                        if ($obj !== null) {
                            $itemId = $obj['item']['id'] ?? null;
                            $buyout = $obj['buyout'] ?? 0;

                            if ($itemId !== null && $buyout > 0 && isset($catalogSet[$itemId])) {
                                $grouped[$itemId][] = [
                                    'unit_price' => (int) $buyout,
                                    'quantity'   => (int) ($obj['quantity'] ?? 1),
                                ];
                            }
                        }

                        // Trim buffer up to past this object
                        $buffer = substr($buffer, $i + 1);
                        $len = strlen($buffer);
                        $i = 0;
                        $objectStart = -1;

                        continue;
                    }
                }

                $i++;
            }

            // If we have an incomplete auction object, keep only from objectStart
            if ($objectStart >= 0) {
                $buffer = substr($buffer, $objectStart);
                $objectStart = 0;
            } elseif ($braceDepth <= $captureDepth) {
                // No open auction object — discard processed buffer
                $buffer = '';
                $objectStart = -1;
            }
        }

        fclose($handle);

        return $grouped;
    }
}
