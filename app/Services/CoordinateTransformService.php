<?php

declare(strict_types=1);

namespace ParcelMap\Services;

use proj4php\Point;
use proj4php\Proj;
use proj4php\Proj4php;

final class CoordinateTransformService
{
    private Proj $source;
    private Proj $target;

    public function __construct(
        private readonly Proj4php $proj4
    ) {
        // Define the source and target coordinate systems.
        $this->source = new Proj(
            'EPSG:5514',
            $this->proj4
        );

        $this->target = new Proj(
            'EPSG:4326',
            $this->proj4
        );
    }

    /*
    Transform one point from EPSG:5514 to EPSG:4326.
     
    @return array{
        longitude: float,
        latitude: float,
        altitude: float|null
    }
    */
    public function transformToWgs84(
        float $x,
        float $y
    ): array {
        // Create a point using the source coordinate system.
        $point = new Point(
            $x,
            $y,
            null,
            $this->source
        );

        // Transform the point to WGS84.
        $transformed = $this->proj4->transform($this->target, $point);

        $coords = $transformed->toArray();

        return [
            'longitude' => (float) $coords[0],
            'latitude' => (float) $coords[1],
            'altitude' => isset($coords[2])
                ? (float) $coords[2]
                : null,
        ];
    }

     // Transform one coordinate pair to GeoJSON format.
     // @return array{0: float, 1: float}

    public function transformToGeoJsonCoordinate(
        float $x,
        float $y
    ): array {
        $result = $this->transformToWgs84($x, $y);

        // GeoJSON coordinates are stored as [longitude, latitude].
        return [
            $result['longitude'],
            $result['latitude'],
        ];
    }

    /*
     Transform a list of EPSG:5514 coordinate pairs
     to GeoJSON coordinate pairs.
     
     @param array<int, array{0: float, 1: float}> $coordinates
     @return array<int, array{0: float, 1: float}>
     */
    public function transformCoordinates(
        array $coordinates
    ): array {
        $result = [];

        // Transform each coordinate pair and collect the results.
        foreach ($coordinates as $coordinate) {
            $result[] = $this->transformToGeoJsonCoordinate(
                $coordinate[0],
                $coordinate[1]
            );
        }

        return $result;
    }
}