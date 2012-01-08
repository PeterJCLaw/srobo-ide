<?php

class CheckoutHelper
{
	private $repo;

	public function __construct(GitRepository $repo)
	{
		$this->repo = $repo;
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
		$intermediateFile = $tmpDir.'/'.basename($destFile);

		// TODO: use plain copy rather than this nasty zip/unzip combo
		$this->repo->archiveSourceZip($intermediateFile, $revision);
		self::unzip($intermediateFile);
		// we've now got a copy of the user's code in the tmpDir folder

		// store the revision of the user's code that's been checked out
		file_put_contents($tmpDir.'/.user-rev', $revision);

		self::createZip($tmpDir, $destFile);

		// remove our temporary folder so that we don't fill up /tmp
		delete_recursive($tmpDir);
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

	private static function getArchiveBuilderLocation()
	{
		$config = Configuration::getInstance();
		$libRobotPath = $config->getConfig('lib_robot.dir');
		$archiveScript = $config->getConfig('lib_robot.archive_script');
		$path = $libRobotPath.'/'.$archiveScript;
		return $path;
	}

	/**
	 * Creates a servable zip file from the given user code.
	 * @param userCodeDir: the location of the users code to include in the zip.
	 * @param destFile: the location to save the resulting zip in.
	 */
	private static function createZip($userCodeDir, $destFile)
	{
		$zipBuilder = self::getArchiveBuilderLocation();

		$s_userCodeDir = escapeshellarg($userCodeDir);
		$s_destFile = escapeshellarg($destFile);
		$s_builder = escapeshellarg($zipBuilder);
		$ret = shell_exec("$s_builder $s_userCodeDir $s_destFile");
		return $ret;
	}
}
