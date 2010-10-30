package org.studentrobotics.ide.checkout;

import java.applet.Applet;
import java.awt.Graphics;
import java.io.IOException;
import java.net.MalformedURLException;
import java.net.URL;
import java.net.URLConnection;
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

	/**
     * constructor on the applet, has to take no arguments b/c applet lifecycle
     * sets up the zipwriterunner and fires it off in a different threadpool
     */
    public CheckoutApplet() {
		mZwr = new ZipWriteRunner();
		ExecutorService dispatcher = Executors.newFixedThreadPool(1);
		dispatcher.submit(mZwr);

	}

    //these are necessary because of the way applets work
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
	 * @param zipUrl
	 *            the url of the zip to download
	 * @return 0 on success, 1 on recoverable failure, -1 on irrecoverable failure
	 */
	public int writeZip(String zipUrl) {
		// we can only write to files with a char[] not a byte[]
		try {
			if (hasDied) {
				return -1;
			}

			try {
				URL url = new URL(zipUrl);
				URLConnection conn = url.openConnection();
				byte[] zipBytes = new byte[conn.getContentLength()];
				conn.getInputStream().read(zipBytes);


				FutureValue<Boolean> result = mZwr.setZip(zipBytes);
				System.err.println("dispatched");
				System.err.println("result future is: " + result);
				Boolean b = result.get(10L);
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
        //caught if a passed url to the applet is bad
		catch (MalformedURLException mue) {
		    mue.printStackTrace();
		    return 1;
		}
        //caught if the applet throws any kind of exception
		catch (IOException ioe) {
		    ioe.printStackTrace();
		    return 1;
		}
        //generic catch is because the security model can deny us access and we won't know
        //so we catch a throwable
		catch (Throwable t) {
			t.printStackTrace();
			return -1;
		}
	}
}
