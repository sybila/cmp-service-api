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
class LaTeX {
    private $string = null;
    public function __construct(string $str)
    {
        $this->string = $str;
    }

    public function __call($function, $args): string
    {
        return $this->string;
    }

    public function __toString(): string
    {
        return $this->string;
    }
}
