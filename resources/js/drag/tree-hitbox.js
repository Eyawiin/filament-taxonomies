export function getDropPlacement({ pointerY, top, height }) {
    const ratio = (pointerY - top) / height

    if (ratio < 0.25) return 'before'
    if (ratio > 0.75) return 'after'

    return 'inside'
}
