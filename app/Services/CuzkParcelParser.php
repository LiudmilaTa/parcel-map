<?php

declare(strict_types=1);

namespace ParcelMap\Services;

use RuntimeException;
use SimpleXMLElement;

final class CuzkParcelParser
{
    private const GML_NAMESPACE = 'http://www.opengis.net/gml/3.2';
    private const CP_NAMESPACE = 'http://inspire.ec.europa.eu/schemas/cp/4.0';

    public function __construct(
        private readonly CoordinateTransformService $coordinateTransformService
    ) {
    }

    /*
    Parse CUZK WFS XML and return GeoJSON FeatureCollection.
    
    @return array<string, mixed>
    */
    public function parse(string $xml): array
    {
        // Convert the XML string into a SimpleXMLElement.
        $document = simplexml_load_string($xml);

        if ($document === false) {
            throw new RuntimeException('Failed to parse CUZK XML.');
        }

        // Register the namespace used to find cadastral parcels.
        $document->registerXPathNamespace('cp', self::CP_NAMESPACE);

        $parcels = $document->xpath('//cp:CadastralParcel');

        if ($parcels === false) {
            throw new RuntimeException('Failed to find cadastral parcels.');
        }

        $features = [];

        foreach ($parcels as $parcel) {
            $feature = $this->parseParcel($parcel);

            if ($feature !== null) {
                $features[] = $feature;
            }
        }

        // Return all parsed parcels as a GeoJSON FeatureCollection.
        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseParcel(SimpleXMLElement $parcel): ?array
    {
        // Register namespaces used to access parcel geometry and data.
        $parcel->registerXPathNamespace('gml', self::GML_NAMESPACE);
        $parcel->registerXPathNamespace('cp', self::CP_NAMESPACE);

        $polygon = $parcel->xpath('./cp:geometry/gml:Polygon');

        if ($polygon === false || count($polygon) === 0) {
            return null;
        }

        $polygon = $polygon[0];

        $coordinates = [];

        $exterior = $polygon->xpath(
            './gml:exterior/gml:LinearRing/gml:posList'
        );

        if ($exterior === false || count($exterior) === 0) {
            return null;
        }

        // Parse the outer boundary of the polygon.
        $exteriorCoordinates = $this->parsePosList(
            (string) $exterior[0]
        );

        if ($exteriorCoordinates === []) {
            return null;
        }

        $coordinates[] = $exteriorCoordinates;

        // Parse inner boundaries (holes) if they exist.
        $interiors = $polygon->xpath(
            './gml:interior/gml:LinearRing/gml:posList'
        );

        if ($interiors !== false) {
            foreach ($interiors as $interior) {
                $interiorCoordinates = $this->parsePosList(
                    (string) $interior
                );

                if ($interiorCoordinates !== []) {
                    $coordinates[] = $interiorCoordinates;
                }
            }
        }

        $attributes = [
            'id' => $this->getGmlId($parcel),
            'label' => $this->getValue($parcel, './cp:label'),
            'nationalCadastralReference' => $this->getValue(
                $parcel,
                './cp:nationalCadastralReference'
            ),
            'areaValue' => $this->getAreaValue($parcel),
        ];

        // Build a GeoJSON Feature from the parsed parcel data.
        return [
            'type' => 'Feature',
            'id' => $attributes['id'],
            'properties' => $attributes,
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => $coordinates,
            ],
        ];
    }

    /*
    Parse GML posList:
    X1 Y1 X2 Y2 X3 Y3 ...
    Convert EPSG:5514 -> EPSG:4326.
    
    @return array<int, array<int, float>>
    */
    private function parsePosList(string $posList): array
    {
        // Split the coordinate string into individual values.
        $values = preg_split('/\s+/', trim($posList));

        if ($values === false || count($values) < 4) {
            return [];
        }

        if (count($values) % 2 !== 0) {
            throw new RuntimeException(
                'Invalid GML posList: expected pairs of coordinates.'
            );
        }

        $coordinates = [];

        for ($i = 0, $count = count($values); $i < $count; $i += 2) {
            $x = (float) $values[$i];
            $y = (float) $values[$i + 1];

            // Convert EPSG:5514 coordinates to GeoJSON coordinates.
            $coordinates[] = $this->coordinateTransformService
                ->transformToGeoJsonCoordinate($x, $y);
        }

        return $coordinates;
    }

    private function getGmlId(SimpleXMLElement $parcel): ?string
    {
        $attributes = $parcel->attributes(
            self::GML_NAMESPACE
        );

        $id = $attributes['id'] ?? null;

        return $id !== null ? (string) $id : null;
    }

    private function getValue(
        SimpleXMLElement $parcel,
        string $xpath
    ): ?string {
        $result = $parcel->xpath($xpath);

        if ($result === false || count($result) === 0) {
            return null;
        }

        $value = trim((string) $result[0]);

        return $value !== '' ? $value : null;
    }

    private function getAreaValue(
        SimpleXMLElement $parcel
    ): ?float {
        $result = $parcel->xpath('./cp:areaValue');

        if ($result === false || count($result) === 0) {
            return null;
        }

        return (float) $result[0];
    }
}