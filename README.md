<!-- markdownlint-disable no-inline-html -->
<p align="center">
  <br><br>
  <img src="https://leafphp.dev/logo-circle.png" height="100"/>
  <br>
</p>

<h1 align="center">Leaf Queue</h1>

<p align="center">
	<a href="https://packagist.org/packages/leafs/queue"
		><img
			src="https://poser.pugx.org/leafs/queue/v/stable"
			alt="Latest Stable Version"
	/></a>
	<a href="https://packagist.org/packages/leafs/queue"
		><img
			src="https://poser.pugx.org/leafs/queue/downloads"
			alt="Total Downloads"
	/></a>
	<a href="https://packagist.org/packages/leafs/queue"
		><img
			src="https://poser.pugx.org/leafs/queue/license"
			alt="License"
	/></a>
</p>
<br />
<br />

Implementing a queuing system from scratch can be a daunting task, and can take a lot of time. For this reason, Leaf aims to provide a unified API for using queues across a variety of different backends, such as Amazon SQS, BeanStalk, Redis, or a database of your choice.

## Basic Usage

In simple terms, this package allows you to push "heavy" tasks into a queue and run them in the background. This is useful for tasks that take a long time to complete, such as sending emails, processing images, etc.

We try to simplify the process of creating and running queues as much as possible, so, for most of the time, you'll only need to create your jobs and run the queue from the command line.

### Creating a Job

Jobs are basically classes that extend the `Leaf\Queues\Job` class. They must implement the `handle` method, which is called when the job is run.

```php
<?php

class ExampleJob extends \Leaf\Queue\Job
{
    public function handle()
    {
        echo "This is being called from the queue!" . date("Y-m-d H:i:s") . "\n";
    }
}
```

### Running the Queue

To run the queue, all you need to do is run the `queue:run` command from the command line. This will run all jobs in the queue.

```bash
leaf queue:run
```

### Pushing Jobs to the Queue

To push a job to the queue, you can use the `dispatch` method on your job class.

```php
ExampleJob::dispatch();
```

## Stay In Touch

-   [Twitter](https://twitter.com/leafphp)
-   [Join the forum](https://github.com/leafsphp/leaf/discussions/37)
-   [Chat on discord](https://discord.com/invite/Pkrm9NJPE3)

## Learning Leaf 3

-   Leaf has a very easy to understand [documentation](https://leafphp.dev) which contains information on all operations in Leaf.
-   You can also check out our [youtube channel](https://www.youtube.com/channel/UCllE-GsYy10RkxBUK0HIffw) which has video tutorials on different topics
-   You can also learn from [codelabs](https://codelabs.leafphp.dev) and contribute as well.

## Contributing

We are glad to have you. All contributions are welcome! To get started, familiarize yourself with our [contribution guide](https://leafphp.dev/community/contributing.html) and you'll be ready to make your first pull request 🚀.

To report a security vulnerability, you can reach out to [@mychidarko](https://twitter.com/mychidarko) or [@leafphp](https://twitter.com/leafphp) on twitter. We will coordinate the fix and eventually commit the solution in this project.

## Sponsoring Leaf

Your cash contributions go a long way to help us make Leaf even better for you. You can sponsor Leaf and any of our packages on [open collective](https://opencollective.com/leaf) or check the [contribution page](https://leafphp.dev/support/) for a list of ways to contribute.

And to all our [existing cash/code contributors](https://leafphp.dev#sponsors), we love you all ❤️

## Links/Projects

-   [Leaf Docs](https://leafphp.dev)
-   [Leaf MVC](https://mvc.leafphp.dev)
-   [Leaf API](https://api.leafphp.dev)
-   [Leaf CLI](https://cli.leafphp.dev)
-   [Aloe CLI](https://leafphp.dev/aloe-cli/)
