<?php

declare(strict_types=1);

namespace ParcelMap\Services;

use RuntimeException;
use SimpleXMLElement;

final class CuzkParcelParser
{
    private const GML_NAMESPACE = 'http://www.opengis.net/gml/3.2';
    private const CP_NAMESPACE = 'http://inspire.ec.europa.eu/schemas/cp/4.0';
    private const BASE_NAMESPACE = 'http://inspire.ec.europa.eu/schemas/base/3.0';
    private const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';
    
    public function __construct(
        private readonly CoordinateTransformService $coordinateTransformService
    ) {
    }

    //Parse CUZK WFS XML and return GeoJSON FeatureCollection.
    public function parse(string $xml): array
    {
        $document = simplexml_load_string($xml);

        if ($document === false) {
            throw new RuntimeException('Failed to parse CUZK XML.');
        }

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

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    // Parse a single CUZK parcel XML document.
    public function parseSingleParcel(string $xml): ?array
    {
        $document = simplexml_load_string($xml);

        if ($document === false) {
            throw new RuntimeException(
                'Failed to parse CUZK parcel XML.'
            );
        }

        $document->registerXPathNamespace(
            'cp',
            self::CP_NAMESPACE
        );

        $parcels = $document->xpath(
            '//cp:CadastralParcel'
        );

        if (
            $parcels === false
            || count($parcels) === 0
        ) {
            return null;
        }

        return $this->parseParcel(
            $parcels[0]
        );
    }

    private function parseParcel(SimpleXMLElement $parcel): ?array
    {
        $parcel->registerXPathNamespace('gml', self::GML_NAMESPACE);
        $parcel->registerXPathNamespace('cp', self::CP_NAMESPACE);
        $parcel->registerXPathNamespace('base', self::BASE_NAMESPACE);
        $parcel->registerXPathNamespace('xlink', self::XLINK_NAMESPACE);

        $polygonResult = $parcel->xpath('./cp:geometry/gml:Polygon');

        if ($polygonResult === false || count($polygonResult) === 0) {
            return null;
        }

        $polygon = $polygonResult[0];

        $exteriorResult = $polygon->xpath(
            './gml:exterior/gml:LinearRing/gml:posList'
        );

        if ($exteriorResult === false || count($exteriorResult) === 0) {
            return null;
        }

        $exteriorCoordinates = $this->parsePosList(
            (string) $exteriorResult[0]
        );

        if ($exteriorCoordinates === []) {
            return null;
        }

        $coordinates = [];
        $coordinates[] = $exteriorCoordinates;

        $interiorResult = $polygon->xpath(
            './gml:interior/gml:LinearRing/gml:posList'
        );

        if ($interiorResult !== false) {
            foreach ($interiorResult as $interior) {
                $interiorCoordinates = $this->parsePosList(
                    (string) $interior
                );

                if ($interiorCoordinates !== []) {
                    $coordinates[] = $interiorCoordinates;
                }
            }
        }

        //Calculate bounding box from transformed coordinates.
        $bbox = $this->calculateBoundingBox(
            $coordinates
        );

        $gmlId = $this->getGmlId($parcel);

        $localId = $this->getValue(
            $parcel,
            './cp:inspireId/base:Identifier/base:localId'
        );

        $label = $this->getValue(
            $parcel,
            './cp:label'
        );

        $nationalCadastralReference = $this->getValue(
            $parcel,
            './cp:nationalCadastralReference'
        );

        $areaValue = $this->getAreaValue(
            $parcel
        );

        $zoningName = $this->getXlinkTitle(
            $parcel,
            './cp:zoning'
        );

        $administrativeUnitName = $this->getXlinkTitle(
            $parcel,
            './cp:administrativeUnit'
        );

        return [
            'type' => 'Feature',

            'id' => $gmlId,

            'properties' => [
                'id' => $gmlId,

                'localId' => $localId,

                'label' => $label,

                'nationalCadastralReference' =>
                    $nationalCadastralReference,

                'areaValue' => $areaValue,

                'zoningName' => $zoningName,

                'administrativeUnitName' =>
                    $administrativeUnitName,

                'minX' => $bbox['minX'],

                'minY' => $bbox['minY'],

                'maxX' => $bbox['maxX'],

                'maxY' => $bbox['maxY'],
            ],

            'geometry' => [
                'type' => 'Polygon',

                'coordinates' => $coordinates,
            ],
        ];
    }

    // Convert GML coordinate pairs from EPSG:5514 to GeoJSON coordinates.
    private function parsePosList(string $posList): array
    {
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

    // Calculate bounding box from GeoJSON coordinates.
    private function calculateBoundingBox(
        array $coordinates
    ): array {
        $minX = INF;
        $minY = INF;

        $maxX = -INF;
        $maxY = -INF;

        foreach ($coordinates as $ring) {
            foreach ($ring as $coordinate) {
                $x = $coordinate[0];
                $y = $coordinate[1];

                $minX = min(
                    $minX,
                    $x
                );

                $minY = min(
                    $minY,
                    $y
                );

                $maxX = max(
                    $maxX,
                    $x
                );

                $maxY = max(
                    $maxY,
                    $y
                );
            }
        }

        return [
            'minX' => $minX,
            'minY' => $minY,
            'maxX' => $maxX,
            'maxY' => $maxY,
        ];
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

    private function getXlinkTitle(
        SimpleXMLElement $parcel,
        string $xpath
    ): ?string {
        $result = $parcel->xpath(
            $xpath
        );

        if (
            $result === false
            || count($result) === 0
        ) {
            return null;
        }

        $attributes = $result[0]->attributes(
            self::XLINK_NAMESPACE
        );

        $title = $attributes['title'] ?? null;

        if ($title === null) {
            return null;
        }

        $value = trim(
            (string) $title
        );

        return $value !== ''
            ? $value
            : null;
    }
}