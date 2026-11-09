# AGENTS.md — ERP Veterinario Independiente

> Instrucciones operativas para agentes de IA (gentle-shell + Gentle-AI v4 · ODD + Engram).
> Responde siempre en español. Código, identificadores y tablas en inglés; textos de UI en español.
> Este archivo dice **cómo trabajar**. **Qué construir** está en `docs/CONSTITUTION.md` y `docs/business/BUSINESS_RULES.md`.

## 0. Regla fundamental
**No inventar el negocio.** Si un comportamiento funcional no está definido, se detiene esa parte y se pregunta (§5). Lo puramente técnico se resuelve de forma simple y coherente con la constitución.

## 1. Qué es
Es un ERP veterinario *self-hosted*: 1 clínica = 1 VPS = 1 BD PostgreSQL, con un solo repositorio y sin forks. La clínica semilla es Servicios Veterinarios San Francisco de Asís (Maracaibo). Están prohibidos `tenant_id` y cualquier abstracción multi-tenant: lo que cambia entre clínicas va en settings.

## 2. Stack y comandos
Laravel 13 · PHP 8.5 · Filament 5 (Livewire 4) para el ERP · Inertia + Vue 3 + TS para el portal · PostgreSQL 18 · Pest · Pint · Larastan.
Consulta la documentación con Context7 **en la versión instalada** (Filament 5 / Livewire 4; la API de Filament v3 no aplica). No asumas APIs de memoria.

El entorno es Sail sobre WSL2. Invócalo siempre como `./vendor/bin/sail`, porque el alias no existe en shells no interactivos.
```bash
sail up -d                          # app, pgsql, mailpit, queue, scheduler
sail composer install && sail pnpm install
sail artisan test --filter=BR-10    # pruebas de una regla
sail artisan test                   # suite completa (BD testing en PostgreSQL)
sail composer types:check           # Larastan nivel 8
sail pint --test                    # formato
sail pnpm run types:check           # vue-tsc
sail pnpm build
sail artisan migrate:fresh --seed   # SOLO en local
```
- Los scripts `types:check` (composer y pnpm) se crean en F0.
- El gestor JS es **pnpm** (nunca npm ni yarn).
- Las pruebas nunca usan SQLite.
- Antes de agregar una dependencia, justificarla y preguntar.

## 3. Documentos y jerarquía
| Nivel | Archivo | Autoridad sobre |
|---|---|---|
| 1 | `docs/CONSTITUTION.md` | Arquitectura y principios |
| 2 | `docs/business/BUSINESS_RULES.md` | Invariantes `BR-xx` y decisiones `DEC-xx` |
| 3 | `docs/features/NNN-slug.md` | Alcance, diseño, tareas y evidencia de una feature |
| 4 | Código y pruebas | Implementación |

- Un nivel inferior nunca contradice a uno superior. Si hay contradicción, detenerse y reportarla.
- Si Engram difiere del repositorio, **manda el archivo del repositorio**.
- `docs/ROADMAP.md` ordena las fases, pero no tiene autoridad sobre el comportamiento.

## 4. Flujo ODD + RDD
### 4.1 Ruteo
| Ruta | Cuándo |
|---|---|
| **Inline**: sin documento ni puerta, con pruebas en verde | El cambio es entendido, de bajo riesgo y se puede retomar desde el diff: un campo, un filtro, un label, estilos, o un bug con su test que lo reproduce |
| **Feature Document + puerta** | No se puede retomar desde el diff (varias sesiones, esperas externas, requisitos largos) **o** toca una zona roja: agenda, dinero y tasas, FEFO, permisos y auditoría, o migraciones de datos en producción |

### 4.2 Feature Document (`docs/features/NNN-slug.md`, una página)
1. **Spec**: el pedido del usuario, copiado literal.
2. **Alcance**: los `BR-xx` que cubre. Nada fuera de ellos.
3. **Decisiones**: los `[DEC-PENDIENTE]` con el formato de §5, cada uno con su "Resuelta: X, fecha" cuando se responde. Si no hay ninguno: "Sin decisiones pendientes".
4. **Diseño técnico**: migraciones, Actions, Queries, permisos y pantallas. No se crea `design.md` ni `plan.md` aparte.
5. **Tareas**: checklist en fases. Cada tarea cita sus BR y sus dependencias (`depende de 2.1`).
6. **Evidencia**: test → resultado, hash del commit y nivel de revisión de cada fase.

### 4.3 Puerta (solo en las features con documento)
Cuando el documento tenga escritos el Diseño técnico y las Tareas, **detenerse y esperar aprobación**. Es una sola aprobación por feature. Después se ejecuta todo seguido, y solo se vuelve a parar ante un `[DEC-PENDIENTE]` nuevo o un cambio al diseño.

### 4.4 Cierre de cada fase (obligatorio y en este orden)
1. Pruebas de la fase (`--filter`), después la suite completa (solo el resumen), y después `types:check`, `pint --test` y `pnpm run types:check`.
2. **Un commit por fase**, convencional y con el ID de la regla: `feat(agenda): reject overlapping appointments [BR-10]`. Sin push ni merge.
3. Ejecutar `gentle-ai review assess` sobre el rango de commits **de esa fase** y anotar el nivel.
   - Si sale `high` (zona roja = 4R), proponer la revisión y esperar decisión humana.
   - Los commits que solo corrigen hallazgos y traen su test no se vuelven a proponer.
4. `mem_save` de las decisiones técnicas y los hallazgos no obvios.
5. Actualizar la Evidencia del Feature Document.

### 4.5 Engram
- **Guardar** decisiones técnicas, hallazgos no obvios y errores recurrentes con su solución. Usar `topic_key` estables (`architecture/*`, `workflow/*`, `module/<módulo>`), para que una decisión que cambia se actualice en lugar de duplicarse.
- **No guardar** credenciales, contenido de `.env`, ni datos de tutores, pacientes o empleados.
- Al cerrar una sesión, ejecutar `mem_session_summary`.

## 5. Cuándo detenerse y cómo preguntar
Se detiene **solo esa parte** del trabajo (las tareas independientes siguen) ante:
- una decisión de negocio faltante;
- un requisito ambiguo o contradictorio;
- una condición sin comportamiento definido;
- una contradicción entre documentos.

No rellenar el hueco con "lo normal en otros ERP".
```text
[DEC-PENDIENTE] <BR o feature afectada>
Contexto: qué se estaba implementando.
Duda: qué no está definido.
Opciones: A) … B) … (con consecuencias)
Recomendación técnica: …
```
La pregunta va en las Decisiones del Feature Document y también en la tabla de `BUSINESS_RULES.md` si afecta una BR. Si la respuesta contradice la constitución, se trata como una decisión arquitectónica, no como un ajuste.

## 6. Pruebas (TDD estricto)
- Primero el test RED, después la implementación y por último el refactor.
- El nombre del test empieza por su ID: `it('BR-10 rejects overlapping appointment for same doctor')`.
- Si se toca un comportamiento existente, se agrega un test que pruebe que el comportamiento anterior se mantiene.
- Las pruebas de autorización se hacen contra el backend (HTTP o la Action), no contra la UI.
- **Prioridad:** agenda, dinero, FEFO, permisos, auditoría y clínica.

## 7. Git
- Las ramas son `feat/NNN-slug` (una por Feature Document; si es grande, varias encadenadas `feat/NNN-slug-parte`), y `fix/…` o `chore/…` para el trabajo inline.
- El agente nunca hace push, merge ni reescribe historia (`rebase`, `amend`, `reset`) sin que se le pida.
- Nunca se versionan `.env`, credenciales ni volcados de BD.

**Definition of Done:** `CONSTITUTION.md` §9 + cierre de fase (§4.4) + confirmación humana.

## 8. Modo autónomo (solo cuando el humano lo indique)
Ante la duda, elegir siempre la opción más conservadora y reversible.
- **Alcance:**
  - como máximo 3 fases por sesión, y solo las tareas del Feature Document en curso;
  - nada de refactors fuera de alcance;
  - no editar `CONSTITUTION.md`, `BUSINESS_RULES.md` ni este archivo.
- **Bloqueos:**
  - un `[DEC-PENDIENTE]` bloquea su tarea y todas las que dependan de ella, sin valores provisionales ni stubs que asuman la respuesta;
  - una fase con tareas bloqueadas queda abierta y no se commitea;
  - si el assess sale `high`, se anota el candidato y se sigue.
- **Git:** un commit por fase. Prohibido push, PR, merge, rebase, cherry-pick, amend, `reset`, `clean -fd`, `branch -D`, `stash drop` y `--force`.
- **Base de datos:**
  - no ejecutar `migrate*`, `db:wipe` ni `db:seed` sobre la BD de desarrollo;
  - prohibido DROP, TRUNCATE y DELETE/UPDATE sin WHERE;
  - las pruebas solo corren contra `testing`, y si `phpunit.xml` no apunta a ella, detenerse.
- **Dependencias e infraestructura:**
  - prohibido `composer require/update/remove`, `pnpm add/update/remove` y editar los lockfiles: si falta una dependencia, se anota y se sigue;
  - no tocar `.env`, `compose.yaml` ni la configuración de Sail;
  - prohibido `sail down -v` y `docker volume rm`.
- **Pruebas:**
  - nunca borrar, saltar ni debilitar pruebas o aserciones;
  - tras 3 intentos fallidos, documentar la tarea y pasar a otra.
- **Efectos externos:**
  - `MAIL_MAILER=log`;
  - no ejecutar `exchange-rate:fetch` ni llamar a WhatsApp ni a ningún servicio real;
  - sin secretos en el código ni en los fixtures.
- **Al terminar:** dejar `.reports/REPORTE-SESION-<n>.md` con las fases cerradas y sus commits, los candidatos a revisión `high` (con su rango), las decisiones técnicas, los bloqueos, las dependencias necesarias y los `[DEC-PENDIENTE]`.
