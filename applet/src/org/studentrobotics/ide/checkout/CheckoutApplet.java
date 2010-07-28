package org.studentrobotics.ide.checkout;

import java.applet.Applet;
import java.awt.Graphics;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

import biz.source_code.base64Coder.Base64Coder;

public class CheckoutApplet extends Applet {

	/**
	 * constant used for serialization
	 */
	private static final long serialVersionUID = 17871929219L;

	private ZipWriteRunner mZwr;

	public static boolean hasDied = false;

	public CheckoutApplet() {
		mZwr = new ZipWriteRunner();
		ExecutorService dispatcher = Executors.newFixedThreadPool(1);
		dispatcher.submit(mZwr);

	}

	public void init() {
	}

	public void stop() {
	}

	public void paint(Graphics g) {
	}

	/**
	 * will attempt to write the passed zip to any found file system with a
	 * .srobo file in its root
	 *
	 * @param zip
	 *            a zip to write
	 * @return false on failure, true on success
	 */
	public int writeZip(String base64Zip) {
		// we can only write to files with a char[] not a byte[]
		try {
			if (hasDied) {
				return -1;
			}
	
			try {
				String decodedString = Base64Coder.decodeString(base64Zip);
				System.err.println(base64Zip + " => " + decodedString);
				FutureValue<Boolean> result = mZwr.setZip(decodedString);
				System.err.println("dispatched");
				System.err.println("result future is: " + result);
				Boolean b = result.get(10);
				System.err.println("result is " + b);
				System.err.println("got result");
				if (hasDied || b == null) {
					return -1;
				}
				if (b)
					return 0;
				else
					return 1;
			} catch (InterruptedException e) {
				e.printStackTrace();
				if (hasDied) {
					return -1;
				}
				return 1;
			}
		}
		catch (Throwable t) {
			t.printStackTrace();
			return -1;
		}
	}
}
