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

    //Insert a new parcel or update an existing parcel by CUZK ID.
    public function save(array $parcel): void
    {
        $sql = <<<'SQL'
            INSERT INTO parcels (
                cuzk_id,
                local_id,
                label,
                national_cadastral_reference,
                area_value,
                zoning_name,
                administrative_unit_name,
                min_x,
                min_y,
                max_x,
                max_y,
                geometry
            )
            VALUES (
                :cuzk_id,
                :local_id,
                :label,
                :national_cadastral_reference,
                :area_value,
                :zoning_name,
                :administrative_unit_name,
                :min_x,
                :min_y,
                :max_x,
                :max_y,
                :geometry
            )
            ON DUPLICATE KEY UPDATE
                local_id = VALUES(local_id),
                label = VALUES(label),
                national_cadastral_reference =
                    VALUES(national_cadastral_reference),
                area_value = VALUES(area_value),
                zoning_name = VALUES(zoning_name),
                administrative_unit_name =
                    VALUES(administrative_unit_name),
                min_x = VALUES(min_x),
                min_y = VALUES(min_y),
                max_x = VALUES(max_x),
                max_y = VALUES(max_y),
                geometry = VALUES(geometry)
            SQL;

        $statement = $this->pdo->prepare($sql);

        $statement->execute([
            'cuzk_id' => $parcel['cuzk_id'],
            'local_id' => $parcel['local_id'],
            'label' => $parcel['label'],
            'national_cadastral_reference' =>
                $parcel['national_cadastral_reference'],
            'area_value' => $parcel['area_value'],
            'zoning_name' => $parcel['zoning_name'],
            'administrative_unit_name' =>
                $parcel['administrative_unit_name'],
            'min_x' => $parcel['min_x'],
            'min_y' => $parcel['min_y'],
            'max_x' => $parcel['max_x'],
            'max_y' => $parcel['max_y'],
            'geometry' => json_encode(
                $parcel['geometry'],
                JSON_THROW_ON_ERROR
            ),
        ]);
    }

    //Get all parcels.
    public function findAll(): array
    {
        $sql = <<<'SQL'
            SELECT
                id,
                cuzk_id,
                local_id,
                label,
                national_cadastral_reference,
                area_value,
                zoning_name,
                administrative_unit_name,
                min_x,
                min_y,
                max_x,
                max_y,
                geometry,
                created_at,
                updated_at
            FROM parcels
            ORDER BY id
            SQL;

        $statement = $this->pdo->query($sql);

        return $statement->fetchAll();
    }

    //Get parcels intersecting a bounding box.
    public function findByBoundingBox(
        float $minX,
        float $minY,
        float $maxX,
        float $maxY
    ): array {
        $sql = <<<'SQL'
            SELECT
                id,
                cuzk_id,
                local_id,
                label,
                national_cadastral_reference,
                area_value,
                zoning_name,
                administrative_unit_name,
                min_x,
                min_y,
                max_x,
                max_y,
                geometry
            FROM parcels
            WHERE
                max_x >= :min_x
                AND min_x <= :max_x
                AND max_y >= :min_y
                AND min_y <= :max_y
            ORDER BY id
            SQL;

        $statement = $this->pdo->prepare($sql);

        $statement->execute([
            'min_x' => $minX,
            'min_y' => $minY,
            'max_x' => $maxX,
            'max_y' => $maxY,
        ]);

        return $statement->fetchAll();
    }   
}