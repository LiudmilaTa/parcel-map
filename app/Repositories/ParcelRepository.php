<?php

declare(strict_types=1);

namespace ParcelMap\Repositories;

use PDO;

final class ParcelRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /*
    Insert a new parcel or update an existing parcel by CUZK ID.
    
    @param array{
        id: string,
        label: string|null,
        nationalCadastralReference: string|null,
        areaValue: float|null,
        geometry: array<string, mixed>
    } $parcel
    */
    public function save(array $parcel): void
    {
        // Insert a new parcel or update it if the CUZK ID already exists.
        $sql = <<<'SQL'
            INSERT INTO parcels (
                cuzk_id,
                label,
                national_cadastral_reference,
                area_value,
                geometry
            )
            VALUES (
                :cuzk_id,
                :label,
                :national_cadastral_reference,
                :area_value,
                :geometry
            )
            ON DUPLICATE KEY UPDATE
                label = VALUES(label),
                national_cadastral_reference = VALUES(national_cadastral_reference),
                area_value = VALUES(area_value),
                geometry = VALUES(geometry)
            SQL;

        // Prepare the query to safely insert the parcel data.
        $statement = $this->pdo->prepare($sql);

        $statement->execute([
            'cuzk_id' => $parcel['id'],
            'label' => $parcel['label'],
            'national_cadastral_reference' =>
                $parcel['nationalCadastralReference'],
            'area_value' => $parcel['areaValue'],
            'geometry' => json_encode(
                $parcel['geometry'],
                JSON_THROW_ON_ERROR
            ),
        ]);
    }
}