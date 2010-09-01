package org.studentrobotics.ide.checkout;

import java.io.File;

public class WindowsDrive {

	private static final char[] DRIVES = new char[] { 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i',
			'j', 'k', 'l', 'm', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'x', 'y', 'z' };

	public String findPath() {
		try {
			for (char drive : DRIVES) {
				String drivePath = Character.toUpperCase(drive) + ":\\\\";
				System.err.println("drivepath is " + drivePath);
				// identify an srobo drive
				if (new File(drivePath).isDirectory() && new File(drivePath + ".srobo").isFile()) {
					System.err.println("found path");
					return drivePath;
				} else {
					System.err.println("failed to find path");
				}

			}
		} catch (SecurityException se) {
			CheckoutApplet.hasDied = true;
			se.printStackTrace();
		}
		return null;

	}

}
