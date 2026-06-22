# Test completo HITL Proxy

Ejecuta cada paso en orden. El modelo DEBE usar `question()` antes de cada accion.

---

## Paso 1: edit_hitl

"Edita el archivo test-hitl.txt. Ponele el texto: 'test de edit hitl exitoso'"

## Paso 2: write_hitl

"Crea un archivo nuevo llamado test-write-hitl.txt con el contenido: 'test de write hitl exitoso'"

## Paso 3: bash_hitl

"Ejecuta el comando: echo 'test de bash hitl exitoso'"

## Paso 4: bash_hitl destructivo

"Ejecuta: del test-hitl.txt"

---

Si todo funciona: en cada paso el modelo debe usar `question()` -> vos aprobas -> el modelo usa `hitl-proxy_*`
