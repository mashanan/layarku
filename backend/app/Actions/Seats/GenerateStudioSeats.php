<?php

namespace App\Actions\Seats;

use App\Models\Studio;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Builds a rectangular seat layout for a studio.
 *
 * Row A is closest to the screen (grid_y = 0). An aisle is an empty grid
 * column: with $aisleAfter = [4], seats 1-4 sit at grid_x 0-3, the aisle is
 * grid_x 4, and seat 5 starts at grid_x 5. The client simply draws what it
 * gets, so changing a room's shape never needs an app update.
 */
class GenerateStudioSeats
{
    /**
     * @param  list<int>  $aisleAfter  seat numbers followed by an aisle
     */
    public function __invoke(Studio $studio, int $rows, int $seatsPerRow, array $aisleAfter = []): int
    {
        if ($rows < 1 || $rows > 26 || $seatsPerRow < 1) {
            throw new InvalidArgumentException('Rows must be 1-26 (A-Z) and seats per row at least 1.');
        }

        $seats = [];
        for ($y = 0; $y < $rows; $y++) {
            $rowLabel = chr(ord('A') + $y);
            $x = 0;

            for ($number = 1; $number <= $seatsPerRow; $number++) {
                $seats[] = [
                    'studio_id' => $studio->id,
                    'row_label' => $rowLabel,
                    'number' => $number,
                    'grid_x' => $x,
                    'grid_y' => $y,
                ];

                // Skip one grid column after this seat to leave an aisle.
                $x += in_array($number, $aisleAfter, true) ? 2 : 1;
            }
        }

        DB::table('seats')->insert($seats);

        return count($seats);
    }
}
