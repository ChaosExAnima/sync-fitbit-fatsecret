<?php

class FoodEntry {
	private string $name;
	private DateTimeInterface $date;
	private string $meal_time;
	private float $units;
	private string $unit_name;

	private float $kcals = 0;
	private float $calcium = 0;
	private float $carbs = 0;
	private float $cholesterol = 0;
	private float $fat = 0;
	private float $fiber = 0;
	private float $iron = 0;
	private float $mono_fat = 0;
	private float $poly_fat = 0;
	private float $sat_fat = 0;
	private float $sodium = 0;
	private float $sugar = 0;
	private float $vit_a = 0;
	private float $vit_c = 0;
	private float $protein = 0;

	public function __construct(
		string $name,
		DateTimeInterface $date,
		string $meal_time,
		float $units,
		string $unit_name,
		array $nutrition = []
	) {
		$this->name      = $name;
		$this->date      = $date;
		$this->meal_time = $meal_time;
		$this->units     = $units;
		$this->unit_name = $unit_name;

		foreach ( $nutrition as $name => $val ) {
			if ( isset( $this->{ $name } ) && 0.0 === $this->{ $name } && is_float( $val ) ) {
				$this->{ $name } = $val;
			}
		}
	}

	public function __get( $key ) {
		return $this->{ $key } ?? null;
	}
}