<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        $city = $this->faker->city();
        $country = Country::factory()->create();

        return [
            'country_id'   => $country->id,
            'raw_location' => "{$city}, {$city}, {$country->name}",
            'city'         => $city,
            'state_region' => $city,
        ];
    }
}
