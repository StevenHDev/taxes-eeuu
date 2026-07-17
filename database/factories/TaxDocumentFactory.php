<?php

namespace Database\Factories;

use App\Enums\TaxDocumentType;
use App\Models\TaxDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxDocument>
 */
class TaxDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'uploaded_by_id' => null,
            'type' => TaxDocumentType::W2,
            'fiscal_year' => (int) date('Y') - 1,
            'title' => fake()->words(3, true),
            'description' => null,
            'ssn_itin' => null,
            'dependent_name' => null,
            'dependent_date_of_birth' => null,
            'amount' => null,
            'file_path' => null,
            'file_original_name' => null,
            'file_mime_type' => null,
            'file_size' => null,
        ];
    }

    public function identification(): static
    {
        return $this->state(fn () => [
            'type' => TaxDocumentType::Identification,
            'ssn_itin' => '123-45-6789',
        ]);
    }

    public function dependent(): static
    {
        return $this->state(fn () => [
            'type' => TaxDocumentType::Dependent,
            'dependent_name' => fake()->name(),
            'dependent_date_of_birth' => fake()->date(),
            'ssn_itin' => '987-65-4321',
        ]);
    }
}
