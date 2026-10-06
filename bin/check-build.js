import { readFileSync } from 'node:fs'
import { execFileSync } from 'node:child_process'

const files = [
    'resources/dist/components/taxonomy-tree.js',
    'resources/dist/components/taxonomy-parent-tree.js',
    'resources/dist/components/taxonomy-assignment-picker.js',
]
const before = files.map((file) => readFileSync(file))
execFileSync(process.execPath, ['bin/build.js'], { stdio: 'inherit' })
for (let index = 0; index < files.length; index++) {
    if (!before[index].equals(readFileSync(files[index]))) {
        throw new Error(
            'Compiled asset differs from committed source: ' + files[index],
        )
    }
}
console.log('All committed bundles reproduce byte for byte.')
