<?php
namespace noahasu\entityAI\helper;

use pocketmine\math\Vector3;

class AlgorithmCalcHelper {
    public static function getPlaneDirection(Vector3 $from, Vector3 $to) : float {
        return sqrt((($to->x - $from->x) ** 2) + (($to->z - $from->z) ** 2));
    }
}