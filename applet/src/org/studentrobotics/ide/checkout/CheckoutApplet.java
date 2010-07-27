package org.studentrobotics.ide.checkout;

import java.applet.Applet;
import java.awt.Graphics;

public class CheckoutApplet extends Applet {

	/**
	 * constant used for serialization
	 */
	private static final long serialVersionUID = 17871929219L;

	private boolean mPressedButton = false;

	public void init() {
		System.out.println("cheeses");
		this.invalidate();
	}

	public void stop() {
	}

	public void paint(Graphics g) {
	}

	/**
	 * will attempt to write the passed zip to any found file system with a
	 * .srobo file in its root
	 * 
	 * @param base64Zip a base64 encoded zip to write
	 * @return false on failure, true on success
	 */
	public boolean writeZip(String base64Zip) {
		return false;
	}
}
