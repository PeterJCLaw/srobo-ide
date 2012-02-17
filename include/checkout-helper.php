<?php

require_once('include/git.php');

class CheckoutHelper
{
	private $repo;
	private $team;

	public function __construct(GitRepository $repo, $team)
	{
		$this->repo = $repo;
		$this->team = $team;
	}

	/**
	 * Builds an archive for the given revision, ready for download.
	 * @param destFile: the location (folder and name) to put the zip file at.
	 * @param revision: the revision id to archive.
	 */
	public function buildZipFile($destFile, $revision)
	{
		// get a fresh tmpdir so there can't possibly be clashes.
		$tmpDir = tmpdir();
		$userTmpDir = $tmpDir.'/user';
		mkdir_full($userTmpDir);
		$intermediateFile = $userTmpDir.'/'.basename($destFile);

		// TODO: use plain copy rather than this nasty zip/unzip combo
		$this->repo->archiveSourceZip($intermediateFile, $revision);
		self::unzip($intermediateFile);
		// we've now got a copy of the user's code in the tmpDir folder

		// store the revision of the user's code that's been checked out
		file_put_contents($userTmpDir.'/.user-rev', $revision);

		$libRobotHash = self::getLibRobotRevisionFor($this->team);
		$zipBuilder = self::getArchiveBuilder($libRobotHash, $tmpDir);

		self::createZip($zipBuilder, $userTmpDir, $destFile);

		// remove our temporary folder so that we don't fill up /tmp
		delete_recursive($tmpDir);
	}

	/**
	 * Gets the revision of libRobot that the team in question should be
	 * served inside their zip.
	 * @param team: The team to find the revision for.
	 * @returns: A hash, or null if the default should be used.
	 */
	public static function getLibRobotRevisionFor($team)
	{
		$teams = Configuration::getInstance()->getConfig('lib_robot.team');
		return @$teams[$team];
	}


	/**
	 * Utility function to unzip an arbitrary file into its current folder,
	 * and remove the original zipfile.
	 * @param zipFile: the path to the file to unzip.
	 * @returns: the output from the unzipping.
	 */
	private static function unzip($zipFile)
	{
		$s_file = escapeshellarg(basename($zipFile));
		$s_path = escapeshellarg(dirname($zipFile));
		$ret = shell_exec("cd $s_path && unzip $s_file");
		unlink($zipFile);
		return $ret;
	}

	/**
	 * Utility to setup the right archive builder for the given hash.
	 * @param hash: The libRobot hash to use.
	 * @param tmpDir: The temporary folder to copy the builder into, if needed.
	 * @returns: The path to the archive builder.
	 */
	private static function getArchiveBuilder($hash, $tmpDir)
	{
		$config = Configuration::getInstance();
		$libRobotPath = $config->getConfig('lib_robot.dir');
		$archiveScript = $config->getConfig('lib_robot.archive_script');

		echo 'hash: '; var_dump($hash);
		$path = $tmpDir.'/libRobot';
		$libRobotRepo = GitRepository::cloneRepository($libRobotPath.'/.git', $path);
		if ($hash !== null)
		{
			$libRobotRepo->checkoutRepo($hash);
		}
		$path .= '/'.$archiveScript;
		return $path;
	}

	/**
	 * Creates a servable zip file from the given user code.
	 * @param userCodeDir: the location of the users code to include in the zip.
	 * @param destFile: the location to save the resulting zip in.
	 */
	private static function createZip($zipBuilder, $userCodeDir, $destFile)
	{
		$s_userCodeDir = escapeshellarg($userCodeDir);
		$s_destFile = escapeshellarg($destFile);
		$s_builder = escapeshellarg($zipBuilder);
		$ret = shell_exec("$s_builder $s_userCodeDir $s_destFile");
		return $ret;
	}
}
