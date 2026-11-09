# CONSTITUTION — ERP Veterinario Independiente

> Leyes no negociables. Romper una exige enmendar primero este archivo en un commit propio (`docs(constitution): …`) y registrar la decisión en Engram. La forma de trabajar del agente está en `AGENTS.md`.

## 1. Producto
- **1 clínica = 1 VPS = 1 BD PostgreSQL 16+.** Un único repositorio maestro, sin forks ni `if ($clinic === 'x')`.
- Están prohibidos `tenant_id`, la selección de empresa y cualquier abstracción multi-tenant. La diferencia entre clínicas se resuelve con settings, no con código.
- Todo lo que varía por clínica se guarda en `spatie/laravel-settings`:
  - `ClinicSettings`: nombre, RIF, dirección, teléfono, logo, paleta (variables CSS) y zona horaria.
  - `BillingSettings`: antigüedad máxima de la tasa y métodos de pago.
  - `ClinicalSettings`: aislamiento de cartera y días de alerta.
  - `NotificationSettings`: horario permitido y antelación de los recordatorios.

  En `.env` solo van secretos e infraestructura.
- **Jerarquía de documentos:** `CONSTITUTION.md` > `BUSINESS_RULES.md` > `docs/features/NNN-*.md` > código y pruebas.
  - Un nivel inferior nunca contradice a uno superior.
  - Si Engram contradice al repositorio, manda el archivo del repositorio.

## 2. Zero-DevOps
| Necesidad | Solución obligatoria | Prohibido |
|---|---|---|
| Caché y sesiones | Driver `database` | Redis, Memcached |
| Colas | Driver `database` (PostgreSQL usa `SKIP LOCKED`) con `after_commit => true` | Horizon, SQS, Beanstalk |
| Concurrencia | `UNIQUE`, `EXCLUDE`, `FOR UPDATE`, `pg_advisory_xact_lock` | `Cache::lock` sobre Redis |
| Tiempo real | `wire:poll.<n>s.visible` en Filament; polling de Inertia en el portal | Reverb, Pusher, Soketi, WebSockets |
| Tareas programadas | Un cron que ejecuta `schedule:run` | Servicios cron adicionales |
| Backups | `spatie/laravel-backup` hacia el Drive o S3 del cliente | — |

- **Procesos permitidos en el VPS:** web, un `queue:work` y el cron.
- El código no usa APIs exclusivas de Redis, para que cambiar de driver siga siendo solo un cambio de `.env`.

## 3. Arquitectura
```
Entrada (Filament | Portal Inertia | Api | Webhook | Job)
   → FormRequest / validación de formato
   → DTO (readonly)
   → Action  ← única dueña de reglas de negocio, escrituras y auditoría
   → Models / PostgreSQL (último guardián de invariantes)
```
```
app/
  Actions/<Módulo>/VerboSustantivoAction.php   # Agenda, Directory, WaitingRoom, Clinical, Grooming,
                                               # Travel, Billing, Inventory, HR, Notifications, Settings
  Queries/<Módulo>/                            # lecturas con regla (fila del médico, aislamiento, alertas)
  DTOs/<Módulo>/XxxData.php
  Enums/                                       # todo status/tipo es backed enum; Permission.php = catálogo único
  Exceptions/Domain/                           # una excepción por regla violada
  Models/  Policies/  Settings/  Support/Audit/
  Filament/{Resources,Pages,Widgets}
  Http/Controllers/{Portal,Api,Webhooks}  Http/Requests/
  Jobs/                                        # solo orquestan: invocan Actions
resources/js/{Pages,Components,types}          # Vue 3 + TS
tests/{Arch,Feature/<Módulo>,Unit}
docs/features/                                 # Feature Documents (formato en AGENTS.md)
```

**Reglas de las Actions**
1. Son `final class` con un único método público `execute()`, entrada tipada (DTO o Model) y retorno tipado.
2. Reciben al `actor` y autorizan dentro, con `Gate::forUser($actor)->authorize(...)`. Así web, bot y la futura app móvil comparten la misma seguridad.
3. Si escriben más de una fila, abren su propio `DB::transaction()`. La auditoría se escribe dentro de esa misma transacción.
4. Solo lanzan excepciones de `App\Exceptions\Domain\*` (que extienden `DomainException`) con `public const RULE = 'BR-XX'`. La capa de entrada las traduce a una notificación de Filament, un error de Inertia o un JSON 422.
5. Pueden componer otras Actions. No conocen HTTP, Livewire ni Filament.

**Lecturas:** los listados simples de Filament pueden usar Eloquent directo. Toda lectura que aplique una regla de negocio va en `app/Queries`.

**Filament:** se sobrescribe `handleRecordCreation()` / `handleRecordUpdate()` para delegar en la Action correspondiente.

**Tests de arquitectura (`tests/Arch`), obligatorios desde F0:**
```php
arch()->expect('App')->toUseStrictTypes();
arch()->expect('App\Actions')->classes()->toBeFinal()->toHaveSuffix('Action');
arch()->expect('App\DTOs')->classes()->toBeReadonly();
arch()->expect(['App\Filament', 'App\Http\Controllers', 'App\Jobs'])
    ->not->toUse(['Illuminate\Support\Facades\DB']);
arch()->expect('App\Actions')->not->toUse(['Livewire', 'Filament', 'Illuminate\Http\Request']);
arch()->expect('App')->not->toUse(['abort', 'abort_if', 'abort_unless', 'dd', 'dump']);
// tests/Arch/NoRoleChecksTest.php: falla si aparece hasRole(, hasAnyRole( o Gate::before en app/
```

## 4. Seguridad y auditoría
- **Permisos, no roles.**
  - Todos los permisos se declaran en el catálogo único `App\Enums\Permission`, con el formato `modulo.accion` en inglés (`appointments.book`, `invoices.void`).
  - Los roles (`admin`, `manager`, `reception`, `doctor`, `owner`) son solo paquetes de permisos que crea el seeder. El código solo pregunta por permisos.
  - El paquete es `spatie/laravel-permission`.
- **Nadie tiene permisos que no se le asignaron:** prohibido `Gate::before` y cualquier superadmin implícito.
- Las denegaciones se hacen siempre con Policy, para que queden auditables. Los 404 salen de `firstOrFail()` o del route model binding.
- **Auditoría inmutable** en `audit_logs`:
  - Registra actor, acción, entidad, id, IP y los valores antes/después (`jsonb`).
  - Un trigger de PostgreSQL rechaza cualquier `UPDATE` o `DELETE` sobre la tabla.
  - Nunca guarda contraseñas, tokens ni hashes.
- **Integraciones después del commit.** Correo y WhatsApp se despachan solo cuando la transacción confirma. El núcleo nunca depende de ellas para mantener su integridad.
- Ni los seeds ni Engram contienen datos reales de tutores, pacientes o empleados.

## 5. Tipado y código
- `declare(strict_types=1);` en todo archivo, PHP 8.3+, Larastan nivel 8 sin agregar baseline nuevo y Pint.
- **El dinero nunca va en `float`.**
  - En PHP se usa `brick/math` (`BigDecimal`, redondeo `HALF_UP`).
  - En PostgreSQL: USD `numeric(14,2)`, VES `numeric(18,2)`, tasa `numeric(20,8)`.
- Vue: `<script setup lang="ts">`, con las props tipadas en `resources/js/types` como espejo de los DTOs.
- **Idioma:** código, identificadores y tablas en inglés; textos de UI en español. Los nombres de los tests empiezan con el ID de la regla: `it('BR-10 rejects …')`.

## 6. Convenciones de PostgreSQL
- La primera migración habilita `btree_gist`, `citext` y `pg_trgm`.
- Tablas en plural `snake_case`, PK con `id()` (bigint identity) y FK explícita, con `restrict` por defecto en datos clínicos y financieros.
- Los tiempos se guardan en `timestamptz`, siempre en UTC, y se muestran en `ClinicSettings::timezone` (`America/Caracas`). Los rangos usan `tstzrange` con límites `[)`.
- Los status se guardan en `varchar` con un `CHECK` igual a los casos del Enum.
- **La BD es la fuente de verdad de las invariantes** (`CHECK`, `UNIQUE` parcial, `EXCLUDE`, FK). La Action solo valida antes para dar un mensaje amigable.
- Mapeo SQLSTATE obligatorio:
  - `23P01` → `SlotUnavailableException`
  - `23505` → `Duplicate*Exception`
  - `40001` → se reintenta una sola vez
- **Una migración aplicada en `main` no se edita nunca**, porque hay instancias desplegadas que ya la corrieron. Cualquier cambio va en una migración nueva.
- Los registros clínicos y financieros no se borran: se cierran, se anulan o se corrigen con un addendum. `SoftDeletes` solo se usa en catálogos.
- JSONB solo para datos variables (anamnesis extendida, requisitos INSAI por país), siempre con índice GIN.
- **Los tests corren contra PostgreSQL real** (BD `testing`). SQLite está prohibido.

## 7. Rendimiento
1. **Polling acotado:** `wire:poll.5s.visible`, solo en el componente de la fila y nunca en la página completa. La consulta usa el índice `(doctor_id, status, priority, checked_in_at)`.
2. **Sin N+1:** `Model::preventLazyLoading()` está activo fuera de producción.
3. **Tablas `cache` y `cache_locks` en `UNLOGGED`:** escriben sin pasar por el WAL, y perder su contenido tras una caída no tiene consecuencias.
4. **Reportes históricos** sobre vistas materializadas, refrescadas de noche por el scheduler con `REFRESH MATERIALIZED VIEW CONCURRENTLY`. Nunca se hacen agregados pesados en vivo.
5. **Producción:** `php artisan optimize`, OPcache activo, `shared_buffers` y `work_mem` ajustados al VPS, y Telescope solo en local.
6. **Meta verificable:** p95 < 300 ms con k6 simulando 15 pantallas en polling y 2 reservas concurrentes, en un VPS de 2 vCPU / 4 GB.

## 8. Principios de ODD
- **No se inventa el negocio.** Un comportamiento funcional no definido se marca `[DEC-PENDIENTE]` y bloquea solo las tareas que dependen de él. Lo técnico se resuelve de forma simple y coherente con este documento.
- El trabajo va **inline** si se puede retomar desde el diff. Necesita **Feature Document** si no se puede, o si toca una zona roja.
  - **Zonas rojas:** agenda, dinero y tasas, FEFO, permisos y auditoría, y migraciones de datos en producción.
- **1 BR = 1 test RED** con el nombre del catálogo. Una BR nueva entra al catálogo en el mismo commit que su test.
- Revisión según riesgo (RDD): pasiva para UI y copy, una lente para Actions fuera de zona roja, y 4R para zonas rojas. Commit, push y release los decide el humano.

## 9. Definition of Done
- [ ] Pest en verde contra PostgreSQL, incluidos los tests de arquitectura.
- [ ] Larastan nivel 8, Pint, `vue-tsc` y `pnpm build` limpios.
- [ ] Cada BR implementada tiene su test con el nombre exacto del catálogo.
- [ ] No hay lógica de negocio fuera de `app/Actions` y `app/Queries`.
- [ ] Las acciones sensibles quedan auditadas y las integraciones salen después del commit.
- [ ] Toda zona roja tocada pasó la revisión 4R y no quedan `[DEC-PENDIENTE]` abiertos en lo entregado.
