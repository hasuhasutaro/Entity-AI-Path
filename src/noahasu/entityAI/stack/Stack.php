<?php
namespace noahasu\entityAI\stack;

class Stack {
    /** @var array<int,mixed> */
    private array $stack = [];

    /**
     * スタックが空かどうかを返します。
     */
    public function isEmpty() : bool {
        return empty($this -> stack);
    }

    /**
     * スタックに渡された要素を追加。
     */
    public function push(mixed $value) : void {
        $this -> stack[] = $value;
    }


    /**
     * スタックの最後の要素を取り出します。
     */
    public function pop() : mixed {
        return array_pop($this -> stack);
    }

    public function count() : int {
        return count($this -> stack);
    }

    public function getAll() : array {
        return $this -> stack;
    }
}