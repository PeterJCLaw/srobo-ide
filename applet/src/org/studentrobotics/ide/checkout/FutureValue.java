package org.studentrobotics.ide.checkout;

import java.util.concurrent.CountDownLatch;
import java.util.concurrent.TimeUnit;

public class FutureValue<T> {
	private T mValue = null;
	private CountDownLatch mLatch = new CountDownLatch(1);

	public T get() throws InterruptedException {
		mLatch.await();
		return mValue;
	}

	public T get(long timeout) throws InterruptedException {
		if (!mLatch.await(timeout, TimeUnit.SECONDS))
			return null;
		return mValue;
	}

	public void set(T value) {
		mValue = value;
		mLatch.countDown();
	}
}
