<?php

use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/** Independent PHP process coordinated by JSON events and explicit stdin commands. */
final class ConcurrencyWorker
{
    private Process $process;

    private InputStream $input;

    private string $buffer = '';

    private array $events = [];

    public function __construct(string $database, string $operation, array $arguments, bool $pause = false, bool $snapshot = false)
    {
        $this->input = new InputStream;
        $this->process = new Process([
            PHP_BINARY, __DIR__ . '/worker.php', $database, $operation,
            json_encode($arguments, JSON_THROW_ON_ERROR), $pause ? '1' : '0', $snapshot ? '1' : '0',
        ], timeout: 25);
        $this->process->setInput($this->input);
        $this->process->start();
    }

    public function send(string $command): void
    {
        $this->input->write($command . "\n");
        // Pump Symfony pipes now; the coordinator may next inspect the database.
        $this->buffer .= $this->process->getIncrementalOutput();
    }

    public function await(string $event): array
    {
        $deadline = microtime(true) + 20;
        do {
            $this->process->checkTimeout();
            $this->buffer .= $this->process->getIncrementalOutput();
            while (($newline = strpos($this->buffer, "\n")) !== false) {
                $line = substr($this->buffer, 0, $newline);
                $this->buffer = substr($this->buffer, $newline + 1);
                $this->events[] = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            }
            foreach ($this->events as $index => $message) {
                if ($message['event'] === $event) {
                    unset($this->events[$index]);
                    if ($event === 'result') {
                        $exitCode = $this->process->wait();
                        $expected = $message['ok'] ? 0 : 1;
                        if ($exitCode !== $expected) {
                            throw new RuntimeException('Worker result did not match its exit status: ' . $this->process->getErrorOutput());
                        }
                    }

                    return $message;
                }
                if ($message['event'] === 'result' && ! $message['ok']) {
                    throw new RuntimeException('Worker failed before ' . $event . ': ' . json_encode($message));
                }
            }
            if (! $this->process->isRunning()) {
                throw new RuntimeException('Worker exited before ' . $event . ': ' . $this->process->getErrorOutput());
            }
            // Polling transports; passing depends on events and observed DB locks, never elapsed sleep.
            usleep(10000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Timed out waiting for worker event ' . $event);
    }

    public function stop(): void
    {
        $this->input->close();
        $this->process->stop(1);
    }
}
