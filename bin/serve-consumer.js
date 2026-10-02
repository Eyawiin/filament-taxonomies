import { readFileSync } from 'node:fs'
import { spawn } from 'node:child_process'

const root = readFileSync('build/consumer-path.txt', 'utf8').trim()
const server = spawn(
    'php',
    ['-S', '127.0.0.1:8012', '-t', root + '/public', 'bin/consumer-server.php'],
    { stdio: 'inherit' },
)
for (const signal of ['SIGTERM', 'SIGINT'])
    process.on(signal, () => server.kill(signal))
server.on('exit', (code) => process.exit(code ?? 1))
