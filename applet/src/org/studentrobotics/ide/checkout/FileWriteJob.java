package org.studentrobotics.ide.checkout;

public class FileWriteJob {
	private String mFileContents;
	private int mCode;
	
	private static int jobs = 0;
	
	public FileWriteJob(String contents) {
		mFileContents = contents;
		mCode = jobs++;
	}
	
	public int getCode() {
		return this.mCode;
	}
	
	public String getContents(){
		return mFileContents;
	}
}
