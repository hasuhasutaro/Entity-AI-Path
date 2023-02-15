<?php
namespace noahasu\entityAI;

use pocketmine\world\Position;

class Node {
    /** @var Position 現在地 */
    private Position $position;

    /** @var float 実コスト */
    private float $gCost;

    /** @var float ヒューリスティックコスト */
    private float $hCost;

    /** @var float ヒューリスティックコストと実コストの合計 */
    private float $fCost;

    /** @var ?Node 親ノード */
    private ?Node $parent;

    /** @var string ノード固有のid */
    private string $id;

    public function __construct(Position $position, float $gCost = 0, float $hCost = 0, ?Node $parent = null) {
        $this -> position = $position;
        $this -> gCost = $gCost;
        $this -> hCost = $hCost;
        $this -> fCost = $gCost + $hCost;
        $this -> parent = $parent;

        $this -> id = $position -> x.'_'.$position -> y.'_'.$position -> z;
    }

    /**
     * 現在地を取得
     */
    public function getPosition() : Position { return $this -> position; }

    /**
     * 現在地までの実コストを取得
     */
    public function getGCost() : float { return $this -> gCost; }

    /**
     * ヒューリスティックコストを取得
     */
    public function getHCost() : float { return $this -> hCost; }

    /**
     * ヒューリスティックコストと実コストの合計を取得
     */
    public function getFCost() : float { return $this -> fCost; }

    /**
     * 親ノードの取得
     */
    public function getParent() : ?Node { return $this -> parent; }

    public function getId() : string { return $this -> id; }
}