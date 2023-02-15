<?php
namespace noahasu\entityAI\entity;

use pocketmine\block\Block;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\math\VoxelRayTrace;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;

use noahasu\entityAI\AStar;
use noahasu\entityAI\helper\AlgorithmCalcHelper;
use noahasu\entityAI\queue\Queue;
use noahasu\entityAI\stack\Stack;

class FollowPathEntity extends Living {
    private int $saveTargetTickCount = 20;
    private int $attackCooldown = 0;
    private ?Vector3 $nextPos = null;
    private ?Vector3 $nextQueuePos = null;
    private Stack $stack;
    private Queue $queue;

    /** @var Stack */
    private ?Stack $path;

    private ?Vector3 $nowToPos = null;

    private float $moveSpeed = 0.4;
    protected float $jumpVelocity = 0.55;

    public static function getNetworkTypeId(): string {
        return EntityIds::PILLAGER;
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(1.8, 0.6);
    }

    public function getName(): string {
        return 'FollowPathEntity';
    }

    public function setMovePosition(Position $to) {
        $this -> path = (new AStar($this -> getPosition(), $to)) -> serch();
        if($this -> path === null) return;
        $this -> nowToPos = $this -> path -> pop();
    }

    public function attack(EntityDamageEvent $source): void {
        parent::attack($source);
        if(!$source instanceof EntityDamageByEntityEvent) return;
        $player = $source -> getDamager();
        if(!$player instanceof Player) return;
        $this -> flagForDespawn();
    }

    protected function entityBaseTick(int $tickDiff = 1): bool {
        if(!$this -> isAlive()) return false;
        $hasUpdate = parent::entityBaseTick($tickDiff);

        if(!isset($this -> nowToPos)) return $hasUpdate;

        if(AlgorithmCalcHelper::getPlaneDirection($this -> getPosition(),$this -> nowToPos) < 0.72) {
            $this -> nowToPos = $this -> path -> pop();
            if(!isset($this -> nowToPos)) {
                return $hasUpdate;
            }
        }

        if($this -> getFootFrontBlock() -> isSolid()) {
            $this -> jump();
        }

        $this -> lookAt($this -> nowToPos -> add(0,1,0));
        
        $this -> createAndSetMotion();

        return $hasUpdate;
    }

    public function getFootFrontBlock() : Block {
        $block = null;
        foreach(VoxelRayTrace::inDirection($this->location, $this->getDirectionVector() -> multiply(0.7), 1) as $vector3) {
            $block = $this->getWorld()->getBlockAt($vector3->x, (int)$this -> location -> y, $vector3->z);
        }
        return $block;
    }

    /**
     * 向いている方向に移動する
     */
    public function createAndSetMotion() : void {
        $speed = 0.3;
        $this -> motion -> x = sin(-deg2rad($this->getLocation() -> getYaw())) * $speed;
        $this -> motion -> z = cos(-deg2rad($this->getLocation() -> getYaw())) * $speed;
    }
}