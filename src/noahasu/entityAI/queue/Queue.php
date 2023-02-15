<?php
namespace noahasu\entityAI\queue;

class Queue {
    /** @var array<int,mixed> */
    private array $queue = [];

    /**
     * キューが空かどうかを返します。
     */
    public function isEmpty() : bool {
        return empty($this -> queue);
    }

    /**
     * キューに渡された要素を追加。
     */
    public function push(mixed $value) : void {
        $this -> queue[] = $value;
    }

    /**
     * キューの最初の要素を取り出します。
     */
    public function pop() : mixed {
        return array_shift($this -> queue);
    }

    public function count() : int {
        return count($this -> queue);
    }

    public function getAll() : array {
        return $this -> queue;
    }
}