<?php
namespace noahasu\entityAI;

use pocketmine\world\Position;
use pocketmine\math\Vector3;
use pocketmine\world\World;

use noahasu\entityAI\helper\AlgorithmCalcHelper;
use noahasu\entityAI\stack\Stack;

class AStar {
    /**デフォルトの2次元ユークリッド距離上限 */
    private const DEFAULT_MAX_SERCH_DISTANCE_WIDTH = 500;
    /** デフォルトの高さ上限 */
    private const DEFAULT_MAX_SERCH_DISTANCE_HEIGHT = 20;
    /** デフォルトのジャンプ可能ブロック数 */
    private const DEFAULT_CAN_JUMP_BLOCK_COUNT = 1;

    /** 探索可能な位置 */
    private const CAN_MOVE_X = [-1, 0, 1];
    private const CAN_MOVE_Z = [-1, 0, 1];

    /** @var Position スタート地点 */
    private Position $start;
    /** @var Vector3 小数点以下切り捨てしたスタート地点 */
    private Vector3 $floorStart;

    /** @var Position ゴール地点 */
    private Position $goal;
    /** @var Vector3 小数点以下切り捨てしたゴール地点 */
    private Vector3 $floorGoal;
    private int $jumpHeight;

    /** 探索可能平面距離上限 */
    private float $maxSerchDistanceWidth;
    /** 探索可能高さ上限 */
    private float $maxSerchDistanceHeight;

    /** @var array<string,Node> 探索可能ノード*/
    private array $openList = [];

    /** @var array<string,Node> 探索済みノード*/
    private array $closeList = [];

    /**
     * @var Position $start スタート地点
     * @var Position $goal ゴール地点
     * @var int $maxSerchDistanceWidth 平面空間上での探索可能ユークリッド距離上限
     * @var int $maxSerchDistanceHeight 探索可能高さ上限
     */
    public function __construct(
        Position $start,
        Position $goal,
        float $maxSerchDistanceWidth = self::DEFAULT_MAX_SERCH_DISTANCE_WIDTH,
        float $maxSerchDistanceHeight = self::DEFAULT_MAX_SERCH_DISTANCE_HEIGHT
    ) {
        $this -> start = $start;
        $this -> floorStart = $start -> floor(); //小数点以下邪魔！
        $this -> goal = $goal;
        $this -> floorGoal = $goal -> floor(); // 小数点以下邪魔！
        $this -> maxSerchDistanceWidth = $maxSerchDistanceWidth;
        $this -> maxSerchDistanceHeight = $maxSerchDistanceHeight;
    }

    /**
     * 経路を探索します
     * 
     * @param bool $returnNearestPath 一番近くまで行った経路のパスを返すかどうか
     * @return Stack
     * @return null
     */
    public function serch(bool $returnNearestPath = false) : ?Stack {
        $startNode = new Node($this -> start, 0, $this -> heuristic($this -> floorStart, $this -> floorGoal)); //探索開始地点なのでコスト0,親ノードなし
        
        $world = $this -> start -> getWorld();
        $checkIsOnBlock = $world -> getBlock($this -> floorGoal) -> isSolid();

        //ゴールブロックが個体ブロックじゃなかったら個体ブロックになるまで下がる
        while(!$checkIsOnBlock) {
            --$this -> floorGoal -> y;
            $checkIsOnBlock = $world -> getBlock($this -> floorGoal) -> isSolid();
        }

        //個体ブロックの一個上のところ = 移動可能なブロックを移動先ブロックとする
        $this -> floorGoal -> y += 1;

        $this -> openList[$startNode -> getId()] = $startNode; //スタートの場所(Node)をopenListにぶちこむ

        while(!empty($this -> openList)) { //開いてるノードが空になるまで処理
            $currentNode = $this -> getBest();

            if($currentNode -> getHCost() < 1) {
                return $this -> getToStart($currentNode);
            }

            unset($this -> openList[$currentNode -> getId()]);
            $this -> closeList[$currentNode -> getId()] = $currentNode;

            $adjacent = $this -> getAdjacentNodes($currentNode);

            foreach($adjacent as $ad) {
                $id = $ad -> getId();
                if(isset($this -> closeList[$id])) {
                    if($this -> closeList[$id] -> getFCost() > $ad -> getFCost()) unset($this -> closeList[$id]);
                    else continue;
                }

                if(isset($this -> openList[$id])) {
                    if($this -> openList[$id] -> getFCost() > $ad -> getFCost()) $this -> openList[$ad -> getId()] = $ad;
                } else {
                    $this -> openList[$ad -> getId()] = $ad;
                }
            }
        }

        if($returnNearestPath) {
            return $this -> getToStart($this -> getNearestPos());
        }
        return null;
    }

    private function getNearestPos() : Node {
        $nearestNode = array_pop($this -> closeList);
        $nearestFCost = $nearestNode -> getFCost();
        foreach($this -> closeList as $id => $node) {
            $cost = $node -> getFCost();
            if($cost < $nearestFCost) {
                $nearestNode = $node;
                $nearestFCost = $cost;
            } else if($cost === $nearestFCost) {
                if($node -> getGCost() <= $nearestNode -> getGCost()) {
                    $nearestNode = $node;
                    $nearestFCost = $cost;
                }
            }
        }

        return $nearestNode;
    }

    private function getToStart(Node $node) : Stack {
        $path = new Stack; //最初にゴールが入るため、Stackを使用。
        
        $currentNode = $node;
        while($currentNode !== null) {
            $path -> push($currentNode -> getPosition() -> add(0.5,0,0.5));
            $currentNode = $currentNode -> getParent();
        }

        return $path;
    }

    /**
     * @return Node[]
     */
    private function getAdjacentNodes(Node $node) : array {
        $adjacentNodes = [];

        $position = $node -> getPosition();
        $floorPos = $position -> floor();
        $x = $floorPos -> x;
        $y = $floorPos -> y;
        $z = $floorPos -> z;
        $world = $position -> getWorld();

        foreach(self::CAN_MOVE_X as $keyX => $cx) {
            foreach(self::CAN_MOVE_Z as $keyZ => $cz) {
                $gCost = 2;
                $nx = ($x + $cx);
                $nz = ($z + $cz);
                $ny = $y;

                if($x === $nx && $z === $nz) continue;

                do {

                    if($ny < $y - 3) continue; // $gCost = 2500;

                    $canMove = $this -> canMove($nx, $ny, $nz, $world);
                    if($canMove === 0) { //移動不可
                        break;
                    }
    
                    else if($canMove === 2) { //ジャンプ移動可

                        $kkX = 0;
                        $kkZ = 0;
                        $kkY = 0;

                        /**
                         * 斜め方向移動のスタック解消
                         * 
                         * □□□
                         * □■□ の移動は直線でいけるが、
                         * ■□□
                         * 
                         * □□□
                         * □■□ の移動時に、
                         * ■B□ (B = ブロック)
                         * 
                         * □□□
                         * ■■□ のような経路をたどる。
                         * ■□□
                         */
                        $diagonalCheck = false;
                        $boolX = false;
                        $boolZ = false;
                        if($keyX === 0 && $keyZ === 0) {
                            // スムーズに斜め移動できる？
                            switch($this -> canMove($nx + 1, $ny , $nz, $world)) {
                                case 2: $boolX = true;
                                break;
                                case 0: $boolX = false;
                                break;
                                case 1: 
                                    if($this -> canMove($nx + 1, $ny - 1, $nz, $world) == 2) {
                                        $boolX = true;
                                        $kkY = -1;
                                    }
                                break;
                            }
                            
                            
                            switch($this -> canMove($nx, $ny, $nz + 1, $world)) {
                                case 2: $boolZ = true;
                                break;
                                case 0: $boolZ = false;
                                break;
                                case 1: 
                                    if($this -> canMove($nx, $ny - 1, $nz + 1, $world) == 2) {
                                        $boolZ = true;
                                        $kkY = -1;
                                    }
                                break;
                            }

                            if(!$boolX && !$boolZ) {
                                break; //進めない
                            }

                            if($boolX && !$boolZ) {
                                $diagonalCheck = true;
                                $kkX = 1;
                                $kkZ = 0;
                            } else if(!$boolX && $boolZ) {
                                $diagonalCheck = true;
                                $kkX = 0;
                                $kkZ = 1;
                            }
                        }
                        else if($keyX === 2 && $keyZ === 2) {
                            //X軸
                            switch($this -> canMove($nx - 1, $ny, $nz, $world)) {
                                case 2: $boolX = true;
                                break;
                                case 0: $boolX = false;
                                break;
                                case 1: 
                                    if($this -> canMove($nx - 1, $ny - 1, $nz, $world) == 2) {
                                        $boolX = true;
                                        $kkY = -1;
                                    }
                                break;
                            }
                            
                            //Z軸
                            switch($this -> canMove($nx, $ny, $nz - 1, $world)) {
                                case 2: $boolZ = true;
                                break;
                                case 0: $boolZ = false;
                                break;
                                case 1: 
                                    if($this -> canMove($nx, $ny - 1, $nz - 1, $world) == 2) {
                                        $boolZ = true;
                                        $kkY = -1;
                                    }
                                break;
                            }

                            if(!$boolX && !$boolZ) {
                                break; //進めない
                            }

                            if($boolX && !$boolZ) {
                                $diagonalCheck = true;
                                $kkX = - 1;
                                $kkZ = 0;
                            } else if(!$boolX && $boolZ) {
                                $diagonalCheck = true;
                                $kkX = 0;
                                $kkZ = - 1;
                            }
                        }
                        else if($keyX === 0 && $keyZ === 2) {
                            //X軸
                            switch($this -> canMove($nx + 1, $ny, $nz, $world)) {
                                case 2: $boolX = true;
                                break;
                                case 0: $boolX = false;
                                break;
                                case 1: 
                                    if($this -> canMove($nx + 1, $ny - 1, $nz, $world) == 2) {
                                        $boolX = true;
                                        $kkY = -1;
                                    }
                                break;
                            }
                            
                            //Z軸
                            switch($this -> canMove($nx, $ny, $nz - 1, $world)) {
                                case 2: $boolZ = true;
                                break;
                                case 0: $boolZ = false;
                                break;
                                case 1: 
                                    if($this -> canMove($nx, $ny - 1, $nz - 1, $world) == 2) {
                                        $boolZ = true;
                                        $kkY = -1;
                                    }
                                break;
                            }

                            if(!$boolX && !$boolZ) {
                                break; //進めない
                            }

                            if($boolX && !$boolZ) {
                                $diagonalCheck = true;
                                $kkX = 1;
                                $kkZ = 0;
                            } else if(!$boolX && $boolZ) {
                                $diagonalCheck = true;
                                $kkX = 0;
                                $kkZ = - 1;
                            }
                        }
                        else if($keyX === 2 && $keyZ === 0) {
                            //X軸
                            switch($this -> canMove($nx - 1, $ny, $nz, $world)) {
                                case 2: $boolX = true;
                                break;
                                case 0: $boolX = false;
                                break;
                                case 1: 
                                    if($this -> canMove($nx - 1, $ny - 1, $nz, $world) == 2) {
                                        $boolX = true;
                                        $kkY = -1;
                                    }
                                break;
                            }
                            
                            //Z軸
                            switch($this -> canMove($nx, $ny, $nz + 1, $world)) {
                                case 2: $boolZ = true;
                                break;
                                case 0: $boolZ = false;
                                break;
                                case 1: 
                                    if($this -> canMove($nx, $ny - 1, $nz + 1, $world) == 2) {
                                        $boolZ = true;
                                        $kkY = -1;
                                    }
                                break;
                            }

                            if(!$boolX && !$boolZ) {
                                break; //進めない
                            }

                            if($boolX && !$boolZ) {
                                $diagonalCheck = true;
                                $kkX = - 1;
                                $kkZ = 0;
                            } else if(!$boolX && $boolZ) {
                                $diagonalCheck = true;
                                $kkX = 0;
                                $kkZ = 1;
                            }
                        }

                        if($diagonalCheck) {
                            $nPos = new Position($nx + $kkX, $ny + 1 + $kkY, $nz + $kkZ, $world);
                            $heuristic = $this -> heuristic($nPos, $this -> floorGoal);
                            if($heuristic !== null) {
                                $n = new node($nPos, $node -> getGCost(), $heuristic, $node);
                                $adjacentNodes[] = $n;
                            }
                            $gCost += 0.1; //コストを増やさないと斜め移動が優先されてしまい、ブロックにぶつかって動かなくなる可能性がある。
                        }

                        

                        $nPos = new Position($nx, $ny + 1 + $kkY, $nz, $world);
                        $heuristic = $this -> heuristic($nPos, $this -> floorGoal);
                        if($heuristic !== false) {
                            $n = new node($nPos, $node -> getGCost() + $gCost, $heuristic, $node);
                            $adjacentNodes[] = $n;
                        }
                        break;
                    }
                } while(--$ny > $y - 10); // yより10ブロック下だったら処理終了 -> 落下死する可能性があるため
            }
        }

        return $adjacentNodes;
    }

    /**
     * いっちばんゴールに近いノードを返す
     */
    private function getBest() : Node {
        $lowestFCostNode = array_pop($this -> openList);
        $this -> openList[$lowestFCostNode -> getId()] = $lowestFCostNode;
        
        foreach($this -> openList as $id => $node) {
            $nodeF = $node -> getFCost(); //ノードのFコスト
            $lowestFCost = $lowestFCostNode -> getFCost(); //現在の最小Fコスト

            if($lowestFCostNode -> getId() === $node -> getId()) continue;

            if($nodeF < $lowestFCost) {
                $lowestFCostNode = $node;
            } else if($node -> getFCost() === $lowestFCostNode -> getFCost()) { //一緒だったら…？
                if($node -> getGCost() <= $lowestFCostNode -> getGCost()) $lowestFCostNode = $node; //実コスト比較
            }
        }

        return $lowestFCostNode;
    }

    /**
     * 3次元空間でのユークリッド距離を取得
     * 
     * もし平面上でのdistanceが上限を超えていたらfalseを返す
     * もし高さが上限を超えていたらfalseを返す
     */
    private function heuristic(Vector3 $from, Vector3 $to) : float|false {
        if(AlgorithmCalcHelper::getPlaneDirection($from, $to) > $this -> maxSerchDistanceWidth) return false;
        if(abs($from -> y - $to -> y) > $this -> maxSerchDistanceHeight) return false;
        return $from -> distance($to);
    }

    /**
     * @return int 0 移動不可
     * @return int 1 移動可能
     * @return int 2 ジャンプして移動可能
     */
    private function canMove(int $tx, int $ty, int $tz, World $world) : int {
        $tWorld = $world;
        $tyIsSolid = $tWorld -> getBlockAt($tx, $ty, $tz) -> isSolid();
        $tyPlus1IsSolid = $tWorld -> getBlockAt($tx, $ty + 1, $tz) -> isSolid(); //二度計算する可能性があるので先に出しておく

        if(!$tyIsSolid && !$tyPlus1IsSolid) {
            return 1;
        }//移動可能
        if($tyIsSolid && !$tyPlus1IsSolid && !$tWorld -> getBlockAt($tx, $ty + 2, $tz) -> isSolid()) {
            return 2;
        }//ジャンプして移動可能
        return 0; //移動不可
    }
}