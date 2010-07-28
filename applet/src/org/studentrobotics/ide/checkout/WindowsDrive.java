package org.studentrobotics.ide.checkout;

import java.io.File;

public class WindowsDrive {

	public static final char[] ALPHABET = new char[] { 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i',
			'j', 'k', 'l', 'm', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'x', 'y', 'z' };

	public String findPath() {
		for (char drive : ALPHABET) {
			String drivePath = Character.toUpperCase(drive) + "\\";

			// identify an srobo drive
			if (new File(drivePath).exists() && new File(drivePath + ".srobo").exists()) {
				return drivePath;
			}

		}
		
		return null;

	}

}
