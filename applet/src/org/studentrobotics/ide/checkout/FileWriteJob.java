package org.studentrobotics.ide.checkout;

public class FileWriteJob {
	private byte[] mFileContents;
	private int mCode;
	
	private static int jobs = 0;
	
	public FileWriteJob(byte[] contents) {
		mFileContents = contents;
		mCode = jobs++;
	}
	
	public int getCode() {
		return this.mCode;
	}
	
	public byte[] getContents(){
		return mFileContents;
	}
}
