package org.studentrobotics.ide.checkout;

import java.util.concurrent.CountDownLatch;

public class FutureValue<T> {
	private T mValue = null;
	private CountDownLatch mLatch = new CountDownLatch(1);

	public T get() throws InterruptedException {
		mLatch.await();
		return mValue;
	}

	public void set(T value) {
		mValue = value;
		mLatch.countDown();
	}
}
