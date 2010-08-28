package org.studentrobotics.ide.checkout;

import java.util.concurrent.CountDownLatch;
import java.util.concurrent.TimeUnit;

/**
 * implements a simple way of getting a value from async computation
 * @author Sam Phippen <samphippen@googlemail.com>
 *
 * @param <T> the type of object being waited on
 */
public class FutureValue<T> {
	private T mValue = null;
	private CountDownLatch mLatch = new CountDownLatch(1);

	/**
	 * gets the value of this future value, blocks potentially forever
	 * @return
	 * @throws InterruptedException
	 */
	public T get() throws InterruptedException {
		mLatch.await();
		return mValue;
	}

	/**
	 * gets the value of this future value, blocks for timeout
	 * @param timeout how long to wait for
	 * @return null if timed out, the value otherwise
	 * @throws InterruptedException
	 */
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
