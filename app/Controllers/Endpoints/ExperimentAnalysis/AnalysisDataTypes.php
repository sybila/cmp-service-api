<?php
class ExperimentId{
    private $value = null;
    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public function __call($function, $args): int
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return strval($this->value);
    }
}

class VariableId{
    private $value = null;
    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public function __call($function, $args): int
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return strval($this->value);
    }
}