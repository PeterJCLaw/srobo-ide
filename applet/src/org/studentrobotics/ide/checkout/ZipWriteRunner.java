package org.studentrobotics.ide.checkout;

import java.io.File;
import java.io.FileWriter;
import java.io.IOException;
import java.util.concurrent.ArrayBlockingQueue;
import java.util.concurrent.ConcurrentHashMap;

public class ZipWriteRunner implements Runnable {
	private ArrayBlockingQueue<FileWriteJob> mWriteQueue = new ArrayBlockingQueue<FileWriteJob>(100);

	private ConcurrentHashMap<Integer, FutureValue<Boolean>> mJobSuccessMap = new ConcurrentHashMap<Integer, FutureValue<Boolean>>();

	public FutureValue<Boolean> setZip(String zip) throws InterruptedException {
		FileWriteJob newJob = new FileWriteJob(zip);
		mWriteQueue.put(newJob);
		FutureValue<Boolean> result = new FutureValue<Boolean>();
		mJobSuccessMap.put(newJob.getCode(), result);
		return result;
	}

	@Override
	public void run() {
		while (true) {
			String path = DriveFinder.findSroboDrive();

			if (path == null) {
				System.err.println("failed to find a drive");

				try {
					Thread.sleep(3000);
				} catch (InterruptedException e) {
					e.printStackTrace();
				}
				
				// notify all waiting jobs that they're not going to happen
				while (!mWriteQueue.isEmpty()) {
					FileWriteJob job = mWriteQueue.poll();
					mJobSuccessMap.get(job.getCode()).set(false);
				}

				continue;
			}

			File f = new File(path + "robot.zip");
			System.err.println("path: " + path + "robot.zip");
			System.err.println("tag1");
			System.err.println("file exists? " + f.exists());
			System.err.println("tag2");

			FileWriteJob job;
			System.err.println("tag3");
			try {
				writeFile(f);
				System.err.println("tag4");
			} catch (InterruptedException e1) {
				e1.printStackTrace();
				job = mWriteQueue.poll();
				if (job != null) {
					mJobSuccessMap.get(job.getCode()).set(false);
				}
				
			}

		}

	}

	private void writeFile(File f) throws InterruptedException {
		FileWriter fw;
		FileWriteJob job;
		System.err.println("tag5");
		job = mWriteQueue.take();
		System.err.println("tag6");
		try {
			fw = new FileWriter(f);
			System.err.println("tag7");
			fw.write(job.getContents());
			System.err.println("tag8");
			fw.flush();
			System.err.println("tag9");
			fw.close();
			System.err.println("tag10");
			System.err.println("setting job with code " + job.getCode() + "to true");
			mJobSuccessMap.get(job.getCode()).set(true);
			System.err.println("tag11");
		} catch (IOException e) {
			e.printStackTrace();
			System.err.println("failed to do io");
			mJobSuccessMap.get(job.getCode()).set(false);
		}
	}

}
