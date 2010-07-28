package org.studentrobotics.ide.checkout;

import java.io.File;
import java.io.FilenameFilter;

public class LinuxDrive {

	public String findPath() {
		// search /mnt/ and /media/*/
		System.err.println("a");
		File mnt = null;
		System.err.println("tag31");
		try {
			System.err.println("tag33");
			mnt = new File("/mnt/.srobo");
			System.err.println("tag32");

			if (mnt != null && mnt.exists()) {
				return "/mnt/";
			}

			String[] searchPaths = new String[] { "/media/", "/Volumes/" };
			for (String searchPath : searchPaths) {
				File media = new File(searchPath);
				if (media.isDirectory()) {
					// only get mountpoints from the media directory
					String[] directories = media.list(new FilenameFilter() {

						@Override
						public boolean accept(File arg0, String arg1) {
							return arg0.isDirectory();
						}
					});
					System.err.println("c");

					for (String s : directories) {
						File srobo = new File(searchPath + s + "/.srobo");
						
						

						if (srobo.exists()) {
							return searchPath + s + "/";
						}

					}

				}
			}
			System.err.println("b");
			return null;
		} catch (SecurityException se) {
			System.err.println("tag34");
			CheckoutApplet.hasDied = true;
			System.err.println("tag35");
			return null;
		}
	}

}
