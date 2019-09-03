<?php

namespace phpbb\cp;

class manager
{
	protected $items;

	public function __construct($items)
	{
		$this->items = $items;
	}

	public function handle()
	{
		$tree = [];

		foreach ($this->items as $item)
		{
			if (get_class($item) === 'phpbb\\acp\\menu\\acp')
			{
				continue;
			}

			$class = null;

			try
			{
				$class = new \ReflectionClass($item);
			}
			catch (\ReflectionException $e)
			{
			}

			$parent = $class->getParentClass();

			$tree[$parent->getShortName()][$class->getShortName()] = $item;
		}

		foreach ($tree['acp'] as $key => $value)
		{
			# Categories ...

			if (!empty($tree[$key]))
			{
				foreach ($tree[$key] as $k => $v)
				{
					# Items / Subcategories

					if (!empty($tree[$k]))
					{
						foreach ($tree[$k] as $id => $item)
						{
							# Sub items
						}
					}
				}
			}
		}
	}
}
