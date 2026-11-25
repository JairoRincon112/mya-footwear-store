<script setup>
// Logo de MyA FOOTWEAR STORE en vector (SVG): se ve nítido a cualquier tamaño.
// Insignia oscura con aro cobre, "MyA" (la M en dorado) y "FOOTWEAR STORE".
// El mismo dibujo está en public/logo.svg y se exporta a PNG para el favicon.
defineProps({
  titulo: { type: String, default: '' },
})
</script>

<template>
  <svg
    class="logo-mya"
    viewBox="0 0 200 200"
    xmlns="http://www.w3.org/2000/svg"
    :role="titulo ? 'img' : null"
    :aria-label="titulo || null"
    :aria-hidden="titulo ? null : 'true'"
  >
    <circle cx="100" cy="100" r="97" fill="#1c1917" />
    <circle cx="100" cy="100" r="97" fill="none" stroke="#c9672e" stroke-width="6" />
    <circle cx="100" cy="100" r="86" fill="none" stroke="#e8c9a6" stroke-width="1.5" opacity=".55" />

    <g transform="translate(100 104) skewX(-8)" font-family="Poppins, 'Arial Black', Arial, sans-serif" font-weight="900" text-anchor="middle">
      <text x="0" y="0" font-size="60" letter-spacing="-2">
        <tspan fill="#e9a23b">M</tspan><tspan fill="#ffffff">yA</tspan>
      </text>
    </g>

    <rect x="66" y="124" width="68" height="3" rx="1.5" fill="#c9672e" />

    <text
      x="100"
      y="145"
      text-anchor="middle"
      font-family="Poppins, 'Arial Black', Arial, sans-serif"
      font-weight="700"
      font-size="11"
      letter-spacing="2.2"
      fill="#e8c9a6"
    >FOOTWEAR STORE</text>
  </svg>
</template>

<style scoped>
.logo-mya {
  display: block;
  width: 100%;
  height: 100%;
}
</style>
