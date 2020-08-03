<?php

namespace App\Helpers;

use App\Exceptions\InvalidTypeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\InvocationStrategyInterface;
use Traversable;

class ArgumentParser implements \ArrayAccess, \IteratorAggregate
{
	/** @var array */
	protected $data;

	public function __construct(?array $args)
	{
		$this->data = $args ?: [];
	}

	public function hasKey(string $key): bool
	{
		return array_key_exists($key, $this->data);
	}

	public function hasValue(string $key): bool
	{
		return $this->get($key) !== null;
	}

	public function checkBy(string $key, string $regex): bool
	{
		return preg_match('@' . $regex . '@', $this->get($key));
	}

	public function get(string $key)
	{
		if (!$this->hasKey($key))
			throw new \Exception('Invalid key ' . $key);

		return $this->data[$key];
	}

	public function getArray(string $key): array
	{
		$value = $this->get($key);
		if (is_array($value))
			return $value;
		else
			throw new InvalidTypeException($key, 'array');
	}

	public function getInt(string $key): int
	{
		$value = $this->get($key);
		if (is_numeric($value))
			return $value;
		else
			throw new InvalidTypeException($key, 'int');
	}

    public function getDateTime(string $key): DateTimeJson
    {
        echo($key);
        return strtotime($key);
    }

	public function getString(string $key): string
	{
		$value = $this->get($key);
		if (is_scalar($value))
			return $value;
		else
			throw new InvalidTypeException($key, 'string');
	}

	public function getFloat(string $key): float
	{
		$value = $this->get($key);
		if ((string)((float)$value) === (string)$value)
			return $value;
		else
			throw new InvalidTypeException($key, 'float');
	}


	public function getBool(string $key): bool
	{
		$value = $this->get($key);
		if ($value === 'true' || $value === 'false')
			return $value == 'true';
		elseif ($value === '1' || $value === '0')
			return (bool)((int)$value);
		else
			throw new InvalidTypeException($key, 'bool');
	}

	// ============================== ArrayAccess

	public function offsetExists($offset)
	{
		return $this->hasKey($offset);
	}

	public function offsetGet($offset)
	{
		return $this->get($offset);
	}

	public function offsetSet($offset, $value)
	{
	}

	public function offsetUnset($offset)
	{
	}

	/**
	 * Retrieve an external iterator
	 * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
	 * @return Traversable An instance of an object implementing <b>Iterator</b> or
	 * <b>Traversable</b>
	 * @since 5.0.0
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->data);
	}
}

class RequestResponseParsedArgs implements InvocationStrategyInterface
{

	/**
	 * Invoke a route callable with request, response and all route parameters
	 * as individual arguments.
	 *
	 * @param array|callable         $callable
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface      $response
	 * @param array                  $routeArguments
	 *
	 * @return mixed
	 */
	public function __invoke(
		callable $callable,
		ServerRequestInterface $request,
		ResponseInterface $response,
		array $routeArguments
	) {
		return call_user_func_array($callable, [$request, $response, new ArgumentParser($routeArguments + $request->getQueryParams())]);
	}
}
