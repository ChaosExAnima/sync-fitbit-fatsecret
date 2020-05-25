<?php

class FoodEntry {
	private string $name;
	private DateTimeInterface $date;
	private int $kcals;
	private string $meal_time;
	private float $units;
	private string $unit_name;
	private float $calcium;
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

	public function __construct(
		string $name,
		DateTimeInterface $date,
		int $kcals,
		string $meal_time,
		float $units,
		string $unit_name,
		float $calcium = 0,
		float $carbs = 0,
		float $cholesterol = 0,
		float $fat = 0,
		float $fiber = 0,
		float $iron = 0,
		float $mono_fat = 0,
		float $poly_fat = 0,
		float $sat_fat = 0,
		float $sodium = 0,
		float $sugar = 0,
		float $vit_a = 0,
		float $vit_c = 0
	) {
		$this->name        = $name;
		$this->date        = $date;
		$this->kcals       = $kcals;
		$this->meal_time   = $meal_time;
		$this->units       = $units;
		$this->unit_name   = $unit_name;
		$this->calcium     = $calcium;
		$this->carbs       = $carbs;
		$this->cholesterol = $cholesterol;
		$this->fat         = $fat;
		$this->fiber       = $fiber;
		$this->iron        = $iron;
		$this->mono_fat    = $mono_fat;
		$this->poly_fat    = $poly_fat;
		$this->sat_fat     = $sat_fat;
		$this->sodium      = $sodium;
		$this->sugar       = $sugar;
		$this->vit_a       = $vit_a;
		$this->vit_c       = $vit_c;
	}

	public function __get( $key ) {
		return $this->{ $key } ?? null;
	}
}