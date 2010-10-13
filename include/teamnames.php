<?php
abstract class TeamNameStrategy {
	abstract public function writeNameChangeRequest($teamId, $newName);

	public static function getDefaultInstance()
	{
		return new PlainTextTeamNameStrategy();
	}
}

class PlainTextTeamNameStrategy extends TeamNameStrategy
{
	private $requestsFile;

	public function __construct()
	{
		$this->requestsFile = Configuration::getInstance()->getConfig("teamNameRequestsFile");
	}

	public function writeNameChangeRequest($teamId, $newName)
	{
		$contents = file_get_contents($this->requestsFile);
		$contents = $contents . "$teamId,$newName\n";
		file_put_contents($this->requestsFile, $contents);
	}
}
