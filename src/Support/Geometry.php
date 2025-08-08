<?php
namespace GfMapBoundary\Support;

class Geometry
{
    public static function decodePolyline(string $encoded): array
    {
        $len = strlen($encoded);
        $index = 0; $lat = 0; $lng = 0; $points = [];
        while ($index < $len) {
            $shift = 0; $result = 0;
            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($index < $len && $b >= 0x20);
            $dlat = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lat += $dlat;

            $shift = 0; $result = 0;
            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($index < $len && $b >= 0x20);
            $dlng = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lng += $dlng;

            $points[] = [$lat * 1e-5, $lng * 1e-5];
        }
        return $points;
    }

    public static function encodePolyline(array $points): string
    {
        $lastLat = 0; $lastLng = 0; $result = '';
        foreach ($points as $p) {
            $lat = (int) round((float) $p[0] * 1e5);
            $lng = (int) round((float) $p[1] * 1e5);
            $dlat = $lat - $lastLat;
            $dlng = $lng - $lastLng;
            $lastLat = $lat; $lastLng = $lng;
            $dlat = ($dlat < 0) ? ~( $dlat << 1 ) : ( $dlat << 1 );
            $dlng = ($dlng < 0) ? ~( $dlng << 1 ) : ( $dlng << 1 );
            foreach ([$dlat, $dlng] as $v) {
                while ($v >= 0x20) {
                    $result .= chr((0x20 | ($v & 0x1f)) + 63);
                    $v >>= 5;
                }
                $result .= chr($v + 63);
            }
        }
        return $result;
    }

    public static function boundsFromPoints(array $points): array
    {
        $minLat = 90.0; $maxLat = -90.0; $minLng = 180.0; $maxLng = -180.0;
        foreach ($points as $p) {
            $lat = (float) $p[0]; $lng = (float) $p[1];
            $minLat = min($minLat, $lat);
            $maxLat = max($maxLat, $lat);
            $minLng = min($minLng, $lng);
            $maxLng = max($maxLng, $lng);
        }
        return [$minLat, $minLng, $maxLat, $maxLng];
    }

    public static function lngToPixelX(float $lng, int $zoom): float
    {
        $lng = max(-180.0, min(180.0, $lng));
        $scale = 256 * pow(2, $zoom);
        return ($lng + 180.0) / 360.0 * $scale;
    }

    public static function latToPixelY(float $lat, int $zoom): float
    {
        $lat = max(-85.05112878, min(85.05112878, $lat));
        $sin = sin(deg2rad($lat));
        $scale = 256 * pow(2, $zoom);
        $y = 0.5 - (log((1 + $sin) / (1 - $sin)) / (4 * M_PI));
        return $y * $scale;
    }

    public static function pixelXToLng(float $x, int $zoom): float
    {
        $scale = 256 * pow(2, $zoom);
        $lng = ($x / $scale) * 360.0 - 180.0;
        return max(-180.0, min(180.0, $lng));
    }

    public static function pixelYToLat(float $y, int $zoom): float
    {
        $scale = 256 * pow(2, $zoom);
        $yy = 0.5 - ($y / $scale);
        $lat_rad = 2.0 * atan(exp($yy * 2.0 * M_PI)) - M_PI / 2.0;
        $lat = rad2deg($lat_rad);
        return max(-85.05112878, min(85.05112878, $lat));
    }

    public static function computeZoomForBounds(array $bounds, int $widthPx, int $heightPx, int $padPx): int
    {
        list($south, $west, $north, $east) = $bounds;
        $usableW = max(1, $widthPx - 2 * $padPx);
        $usableH = max(1, $heightPx - 2 * $padPx);
        $maxZoom = 20; // keep <= 20
        $best = 0;
        for ($z = $maxZoom; $z >= 0; $z--) {
            $x1 = self::lngToPixelX($west, $z);
            $x2 = self::lngToPixelX($east, $z);
            $y1 = self::latToPixelY($north, $z);
            $y2 = self::latToPixelY($south, $z);
            $spanX = abs($x2 - $x1);
            $spanY = abs($y2 - $y1);
            if ($spanX <= $usableW && $spanY <= $usableH) { $best = $z; break; }
        }
        return $best;
    }
}
