# ROADMAP — ERP Veterinario Independiente

> Cada fase termina en algo **usable en la clínica**. FD = Feature Document (`docs/features/NNN-*.md`). Una fase no arranca con `DEC` abiertos que la bloqueen.

| Fase | Entrega | BRs | DEC antes de empezar | FD | Sale cuando… |
|---|---|---|---|---|---|
| **F0 · Cimientos** | Laravel 12 + PostgreSQL 16 en Sail (`btree_gist`, `citext`, `pg_trgm`), drivers `database`, settings, catálogo `Permission`, `audit_logs` con trigger, panel Filament con la marca SFA, tests de arquitectura, scripts de composer y pnpm de AGENTS.md | BR-01–05, 56, 57 | — | Sí (permisos y auditoría) | La suite pasa en verde con los tests de arquitectura incluidos, y el logo y los colores cambian desde Ajustes |
| **F1 · Directorio + Sala de espera** | Tutores y mascotas, alta de urgencia, link de contraseña después del commit, check-in, fila con `wire:poll.5s.visible` | BR-06–09, 18–23 | DEC-01 | No | El médico ve un check-in sin cita en ≤ 5 s **y k6 da p95 < 300 ms** (15 pantallas en polling + 2 reservas concurrentes) |
| **F2 · Agenda + Portal + Web pública** | `DoctorSchedule`, `BookAppointmentAction` con GIST, portal Inertia/Vue, web institucional con SEO local | BR-10–17 | — | **Sí** (zona roja) | BR-11 (concurrencia) pasa y una cita reservada en el portal aparece en recepción |
| 💰 **Hito 2 (30 %)** | Demo: Pacientes + Sala de espera + Citas | | | | |
| **F3 · Clínica** | Consulta, vacunas con cálculo de refuerzo, adjuntos, récipe en PDF, peluquería | BR-24–30 | DEC-01 | No | Un médico atiende de punta a punta y entrega el récipe |
| **F4 · Caja + Inventario** | Tasa automática y manual, caja, facturación bimoneda, pagos, egresos, arqueo, lotes FEFO, alertas | BR-36–47, 58 | DEC-03, 04, 07 | **Sí** (zona roja) | Un día simulado cuadra al céntimo y el stock coincide con la suma de sus movimientos |
| **F5 · WhatsApp** | Cola escalonada, recordatorios, webhook del bot conectado a `BookAppointmentAction` | BR-51–55 | DEC-05 | **Sí** (espera externa) | 50 recordatorios salen espaciados y una reserva hecha por el bot bloquea el slot |
| **F6 · INSAI** | Expediente de viaje, requisitos por destino, validador | BR-31–35 | DEC-02 | Sí (requisitos que entrega la clínica) | Un expediente real queda `ready` o lista exactamente lo que le falta |
| **F7 · Nómina** | Empleados, comisiones congeladas, períodos y recibos | BR-48–50 | DEC-06, 08 | No | La nómina de un mes cuadra con las facturas pagadas |
| **F8 · Despliegue** | VPS con Coolify, backups nocturnos, `optimize`, tuning de PostgreSQL, importación desde Excel, capacitación | — | — | Sí (migración de datos) | El backup se restaura en un VPS limpio, k6 se repite en el VPS real y la clínica opera un día sin soporte |
| 💰 **Hito 3 (30 %)** | Producción + capacitación | | | | |

**Fuera de alcance (Fase 2):** API móvil (Sanctum + Scramble) montada sobre las mismas Actions.

**Reglas**
- Un requisito nuevo a mitad de fase entra primero como BR con su test RED. Nunca se cuela sin test.
- Al cerrar cada fase se aplica el checklist de AGENTS.md §4.4.
