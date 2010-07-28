package org.studentrobotics.ide.checkout;

public class DriveFinder {
	private static DriveFinder instance = null;
	
	
	public static String findSroboDrive() {
		DriveFinder df = getInstance();
		System.err.println("df is " + df.toString());
		return df.findPath();
		
	}
	
	private String findPath() {
		OperatingSystem os = getOS();
		System.err.println("os is " + os.toString());
		
		if (os == OperatingSystem.windows) {
			return new WindowsDrive().findPath();
		} else if (os == OperatingSystem.nix) {
			return new LinuxDrive().findPath();
		} else {
			CheckoutApplet.hasDied = true;
			return null;
		}
		
	}

	private static DriveFinder getInstance() {
		if (instance == null) {
			instance = new DriveFinder();
		}
		
		return instance;
	}

	public OperatingSystem getOS(){
		String os = System.getProperty("os.name").toLowerCase();
		if (os.contains("win")) {
			return OperatingSystem.windows;

		// do a check for *nix (including apple)
		} else if (os.contains("nix") || os.contains("nux") || os.contains("mac")) {
			return OperatingSystem.nix;
		} else {
			return OperatingSystem.unknown;
		}
	}
	
}
