package org.studentrobotics.ide.checkout;

/**
 * Represents a file that is to be written, contains a return status code
 *
 * @author Sam Phippen <samphippen@googlemail.com>
 */
public class FileWriteJob {
	private byte[] mFileContents;
	private int mCode;

	private static int jobs = 0;

    /**
     * creates a new job and fills it's contents
     * also gives the job a unique id
     */
	public FileWriteJob(byte[] contents) {
		mFileContents = contents;
		mCode = jobs++;
	}

    /**
     * gets the code of the file write job
     */
	public int getCode() {
		return this.mCode;
	}

    /**
     * gets the contents of the file write job
     */
	public byte[] getContents(){
		return mFileContents;
	}
}
