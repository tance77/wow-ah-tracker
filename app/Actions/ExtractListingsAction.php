<?php

declare(strict_types=1);

namespace App\Actions;

class ExtractListingsAction
{
    /**
     * Stream through a commodities JSON file and extract listings for the given item IDs.
     *
     * Reads the file in chunks, matching auction objects and filtering to only
     * the requested items. Memory stays bounded because each batch targets a
     * small subset of items.
     *
     * @param  string  $filePath  Path to the downloaded commodities JSON file
     * @param  int[]   $itemIds   Blizzard item IDs to extract
     * @return array<int, array<array{unit_price: int, quantity: int}>>  Listings grouped by item ID
     */
    public function __invoke(string $filePath, array $itemIds): array
    {
        $handle = fopen($filePath, 'r');
        $catalogSet = array_flip($itemIds);
        $grouped = [];
        $buffer = '';

        while (! feof($handle)) {
            $buffer .= fread($handle, 131072); // 128KB chunks

            // Match each top-level auction object containing an item sub-object.
            while (preg_match('/\{[^{}]*\{"id":(\d+)\}[^{}]*\}/', $buffer, $match, PREG_OFFSET_CAPTURE)) {
                $fullMatch = $match[0][0];
                $offset = $match[0][1];
                $itemId = (int) $match[1][0];

                if (isset($catalogSet[$itemId])) {
                    $entry = json_decode($fullMatch, true);
                    if ($entry) {
                        $grouped[$itemId][] = [
                            'unit_price' => (int) ($entry['unit_price'] ?? 0),
                            'quantity'   => (int) ($entry['quantity'] ?? 0),
                        ];
                    }
                }

                // Advance buffer past this match
                $buffer = substr($buffer, $offset + strlen($fullMatch));
            }

            // Aggressively trim buffer: keep only from the last opening brace onward,
            // which may be a partial object. This prevents unbounded buffer growth
            // from non-matching objects accumulating.
            $lastOpenBrace = strrpos($buffer, '{');
            if ($lastOpenBrace !== false && $lastOpenBrace > 0) {
                $buffer = substr($buffer, $lastOpenBrace);
            } elseif (strlen($buffer) > 4096) {
                // No opening brace found and buffer is large — discard it
                $buffer = '';
            }
        }

        fclose($handle);

        return $grouped;
    }
}
