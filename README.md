# leaky-bucket

An async Leaky Bucket implementation using the Revolt EventLoop

## Usage

```php
$bucket = new Nadylib\LeakyBucket\LeakyBucket(size: 5, refillDelay: 1.0, refillAmount: 1);
```

There is only 1 function: `LeakyBucket::take(<amount>)`, which will wait until there is at least `<amount>` in the bucket, and then take it. If the bucket is already full, no waiting is needed.
