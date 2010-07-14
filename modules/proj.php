<?php

class ProjModule extends Module
{
	public function __construct()
	{
		$this->installCommand('list', function() {
			throw new Exception("unimplemented", 3);
		});
	}
}
