<?php

namespace Tests\Unit;

use App\Models\GradeWeight;
use App\Services\ScoreCalculator;
use PHPUnit\Framework\TestCase;

class ScoreCalculatorTest extends TestCase
{
    public function test_empty_component_is_not_treated_as_zero_and_returns_null(): void
    {
        $this->assertNull(ScoreCalculator::nilaiAkhir(85, 80, null));
        $this->assertNull(ScoreCalculator::nilaiAkhir(null, 80, 90));
        $this->assertNull(ScoreCalculator::nilaiAkhir(85, null, 90));
        $this->assertNull(ScoreCalculator::nilaiAkhir(null, null, null));
    }

    public function test_default_formula_averages_three_components_and_rounds_to_nearest_integer(): void
    {
        // (80 + 85 + 90) / 3 = 85
        $this->assertSame(85, ScoreCalculator::nilaiAkhir(80, 85, 90));
        // (80 + 81 + 81) / 3 = 80.666... -> 81
        $this->assertSame(81, ScoreCalculator::nilaiAkhir(80, 81, 81));
        // (80 + 80 + 81) / 3 = 80.333... -> 80
        $this->assertSame(80, ScoreCalculator::nilaiAkhir(80, 80, 81));
        // Nilai 0 eksplisit tetap dihitung (bukan null)
        $this->assertSame(60, ScoreCalculator::nilaiAkhir(0, 90, 90));
    }

    public function test_custom_weights_calculate_weighted_average(): void
    {
        $w = new GradeWeight(['mode' => 'bobot', 'bobot_sumatif' => 40, 'bobot_asts' => 30, 'bobot_asas' => 30]);
        // 80*0.4 + 70*0.3 + 90*0.3 = 32 + 21 + 27 = 80
        $this->assertSame(80, ScoreCalculator::nilaiAkhir(80, 70, 90, $w));
    }
}
