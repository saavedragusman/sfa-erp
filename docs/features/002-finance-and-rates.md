# 002 · Permisos financieros y tasa de cambio (BR-02, BR-05)

Rama: `feat/002-finance-and-rates` (apilada sobre `chore/f0-foundations`) · Zona roja: permisos y dinero/tasas (4R en el cierre de cada fase) · Estado: **puerta aprobada 2026-11-09 (DEC-12 = A, DEC-13 = A); en ejecución**

## 1. Spec
Pedido del usuario, literal:

> el f0 ya esta cerrado entonces?
> si, cierra F0 con la opción 2

Opción 2, tal como se propuso: "Mover BR-03 y BR-04 a F1 y cerrar F0 con BR-02 y BR-05. Es más coherente con el orden de las tablas, pero cambia el ROADMAP y hay que anotarlo."

BR-02, literal (`docs/business/BUSINESS_RULES.md`):

> `finance.view_global`, `expenses.manage`, `payroll.manage` y `cash.view_all` solo se siembran en `admin` y `manager`.
> Policies + `Gate` en la Action → `AuthorizationException`

BR-05, literal:

> Hay una sola tasa por `effective_date` (`UNIQUE`), con `rate > 0` y `source ∈ {auto, manual}`. Cargarla exige `exchange_rates.manage`. Una tasa ya usada en una factura o egreso no se puede editar.
> `SetExchangeRateAction` → `ExchangeRateLockedException`

## 2. Alcance
- **Cubre:** BR-02 (catálogo y siembra), BR-05 (tabla, Action, permiso y bloqueo, según DEC-13) y BR-57 en "cargar o editar tasa".
- **Se mueve a F1** (ROADMAP): BR-03 y BR-04. Sus tablas (tutores, pacientes, citas, consultas) no existen hasta F1 y F2; escribir sus Policies ahora sería inventar sobre tablas inexistentes.
- **Fuera de alcance:** pantalla de tasas (F4), `exchange-rate:fetch` y su alerta (BR-58, DEC-04), vigencia y antigüedad de la tasa (BR-37, DEC-03), y las Actions de egresos y nómina (F4 y F7).

## 3. Decisiones (puerta aprobada 2026-11-09)
```text
[DEC-PENDIENTE] BR-05
Contexto: BR-05 nombra el permiso `exchange_rates.manage`, pero ninguna BR dice a qué roles se siembra
          (BR-02 lista solo los cuatro financieros).
Duda: ¿qué roles reciben `exchange_rates.manage`?
Opciones: A) admin y manager. B) Solo admin.
Recomendación técnica: A. BR-58 hace que el `manager` sea quien recibe la alerta si la tasa automática falla,
          y la carga manual es su respaldo: sin el permiso no podría actuar.
Resuelta: A, 2026-11-09.

[DEC-PENDIENTE] BR-05
Contexto: "Una tasa ya usada en una factura o egreso no se puede editar" depende de `invoices` y `expenses` (F4),
          que aún no existen.
Duda: ¿cómo se entrega ese bloqueo en F0?
Opciones: A) Se deja para F4: F0 entrega tabla, Action, permiso y auditoría; `ExchangeRateLockedException` y su test
          RED llegan con `IssueInvoiceAction`.
          B) Se agrega ahora una consulta de "uso" que hoy devuelve siempre false.
Recomendación técnica: A. B sería código muerto y un test que no prueba nada real.
Resuelta: A, 2026-11-09.
```
Asunción técnica mía, sin decisión de negocio: en F0, BR-02 entrega catálogo y siembra. Sus pruebas de denegación (`it('BR-02 denies expenses and payroll without finance permissions')`) se escriben junto a cada Action de egresos (F4) y nómina (F7), porque hoy no existe ninguna Action que autorizar.

## 4. Diseño técnico
- **`Enums/Permission`** suma `FinanceViewGlobal`, `ExpensesManage`, `PayrollManage`, `CashViewAll` y `ExchangeRatesManage`.
- **`RolesAndPermissionsSeeder`:** `admin` recibe todos. `manager` recibe los cuatro de BR-02 y `exchange_rates.manage` (si DEC-12 = A). `reception`, `doctor` y `owner` no reciben ninguno.
- **Migración `exchange_rates`:**
  - `id`, `effective_date date UNIQUE`, `rate numeric(20,8)` con `CHECK (rate > 0)`, `source varchar` con `CHECK (source IN ('auto','manual'))`, `timestamps`.
  - Sin FK. La auditoría guarda al actor.
- **`Enums/ExchangeRateSource`** (`auto`, `manual`) y `Models/ExchangeRate` (la tasa se lee como `string`, nunca `float`).
- **`DTOs/Settings/ExchangeRateData`** (`readonly`): `effectiveDate`, `rate` (string) y `source`.
- **`Policies/ExchangeRatePolicy::manage`:** solo pregunta por `exchange_rates.manage`.
- **`Actions/Settings/SetExchangeRateAction::execute(User $actor, ExchangeRateData $data): ExchangeRate`:**
  1. Autoriza con `Gate::forUser($actor)->authorize('manage', ExchangeRate::class)`.
  2. Valida con `brick/math` que `rate > 0` y que tenga como máximo 8 decimales.
  3. Dentro de `DB::transaction`, crea la tasa de esa fecha o edita la existente, y audita `exchange_rate.created` o `exchange_rate.updated` con antes y después.
  4. Mapea `23505` sobre `effective_date` a `DuplicateExchangeRateException` (`RULE = 'BR-05'`): cubre dos cargas concurrentes de la misma fecha.
- **Sin pantalla** en F0.

**Invariantes de seguridad:** sin `hasRole` ni `Gate::before`; un `admin` sin el permiso queda denegado; la BD es la fuente de verdad (`UNIQUE` y `CHECK`).

## 5. Tareas
**Fase 1 · Permisos (BR-02)**
- [ ] 1.1 RED: `BR-02 seeds finance permissions only to admin and manager` y `BR-02 grants no finance permission to reception, doctor or owner`. Test de comportamiento previo: los permisos de BR-01 siguen igual.
- [ ] 1.2 Casos de `Permission` y mapa del seeder.

**Fase 2 · Tabla (BR-05)** (zona roja: dinero)
- [ ] 2.1 RED: `BR-05 rejects a second rate for the same date`, `BR-05 rejects a zero or negative rate`, `BR-05 rejects an unknown source`.
- [ ] 2.2 Migración, `ExchangeRateSource` y `ExchangeRate`.

**Fase 3 · Action (BR-05, BR-57)** (zona roja; depende de 1.x y 2.x)
- [ ] 3.1 RED:
  - `BR-05 forbids loading a rate without exchange_rates.manage`.
  - `BR-05 lets manager load a rate` (según DEC-12).
  - `BR-05 creates then edits the rate of a date and audits actor, ip and before/after`.
  - `BR-05 rejects a non-positive rate or more than 8 decimals`.
  - `BR-05 maps a concurrent duplicate date to DuplicateExchangeRateException`.
- [ ] 3.2 `ExchangeRateData`, `ExchangeRatePolicy`, `DuplicateExchangeRateException` y `SetExchangeRateAction`.

**Cierre de F0**
- [ ] 4.1 `docs/ROADMAP.md`: F0 = BR-01, 02, 05, 56, 57 y F1 suma BR-03 y BR-04. Se registran DEC-12 y DEC-13 en `BUSINESS_RULES.md`.
- [ ] 4.2 Checklist de cierre de §4.4 para F0, y confirmación humana (push y merge).

## 6. Evidencia
(vacía hasta ejecutar)
