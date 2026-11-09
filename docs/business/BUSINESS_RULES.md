# BUSINESS_RULES — Catálogo de invariantes

> - Cada BR tiene un test RED en `tests/Feature/<Módulo>/<Action>Test.php` cuyo nombre empieza por su ID, así que `sail pest --filter=BR-10` lo encuentra.
> - Los IDs son estables: nunca se renumeran y las reglas nuevas se agregan al final.
> - `⚙` = valor que se lee de los settings. `⚠ DEC-xx` = la regla depende de una decisión pendiente (ver el final): sus tareas no se implementan hasta resolverla.

## M0 · Ajustes, permisos y auditoría
| ID | Invariante | Action → Excepción | Test RED |
|---|---|---|---|
| BR-01 | Crear o editar personal exige `staff.manage`. Asignar el rol `admin` exige además `staff.manage_admins`. Editar o degradar a un `admin` existente también exige `staff.manage_admins`. El personal puede tener varios roles de staff, pero `owner` nunca se combina con ellos. El seeder solo da estos permisos a `admin`, y a `manager` solo el primero. | `CreateStaffMemberAction`, `UpdateStaffMemberAction` → `AuthorizationException`, `InvalidStaffRolesException`, `DuplicateStaffEmailException` | `it('BR-01 forbids users without staff.manage from creating staff')`, `it('BR-01 forbids manager from editing an existing admin')`, `it('BR-01 rejects staff with an email that already exists')` |
| BR-02 | `finance.view_global`, `expenses.manage`, `payroll.manage` y `cash.view_all` solo se siembran en `admin` y `manager`. | Policies + `Gate` en la Action → `AuthorizationException` | `it('BR-02 denies expenses and payroll without finance permissions')` |
| BR-03 | Un tutor solo accede a sus propias mascotas, citas y carnet (ownership en la Policy). | `Queries\Portal\*` → `AuthorizationException` | `it('BR-03 prevents an owner from viewing another owner patient')` |
| BR-04 | Con ⚙`doctor_patient_isolation = true`, un médico solo ve los pacientes que tiene asignados por una cita, una entrada en sala o una consulta previa. Con `false` ve a todos. | `Queries\Clinical\AccessiblePatientsQuery` | `it('BR-04 hides unassigned patients from doctor when isolation mode is on')` |
| BR-05 | Hay una sola tasa por `effective_date` (`UNIQUE`), con `rate > 0` y `source ∈ {auto, manual}`. Cargarla exige `exchange_rates.manage`. Una tasa ya usada en una factura o egreso no se puede editar. | `SetExchangeRateAction` → `ExchangeRateLockedException` | `it('BR-05 rejects editing an exchange rate already used by an invoice')` |
| BR-56 | `audit_logs` solo admite inserciones: un trigger rechaza `UPDATE` y `DELETE`. Nunca guarda contraseñas ni tokens. | Trigger PG → `QueryException` | `it('BR-56 rejects updating or deleting an audit log row')` |
| BR-57 | Las acciones sensibles auditan, en la misma transacción, al actor, la IP y los valores antes/después. Son: anular factura, cerrar caja, cargar o editar tasa, ajustar stock, addendum clínico, emitir récipe, cambiar permisos o personal, y el estado de un expediente INSAI. | Cada Action sensible | `it('BR-57 writes an audit entry with before and after values when an invoice is voided')` |
| BR-58 | ⚠ DEC-04. `exchange-rate:fetch` corre a la ⚙hora configurada y guarda la tasa con `source=auto`. Si falla, no inventa ni copia una tasa: avisa al `manager` y la carga manual queda disponible. | `FetchExchangeRateAction` → `ExchangeRateFetchFailedException` | `it('BR-58 stores no rate and alerts manager when fetch fails')` |
| BR-59 | El personal nuevo recibe un link para definir su contraseña, enviado **solo después del commit**, y que expira según el broker de contraseñas (sin contraseña inicial que circule entre personas). Editar personal no reenvía el link. | `CreateStaffMemberAction` | `it('BR-59 sends set-password link to new staff only after commit')` |

## M1 · Directorio
| ID | Invariante | Action → Excepción | Test RED |
|---|---|---|---|
| BR-06 | El `document_id` normalizado (`V-/E-/J-` + dígitos) es único. El WhatsApp en E.164 es obligatorio. El email (`citext`) es opcional y único cuando existe. | `RegisterOwnerAction` → `DuplicateOwnerException` | `it('BR-06 rejects a second owner with the same document id')` |
| BR-07 | Quien tenga `owners.create` y `patients.create` (sembrados en `reception`, `manager` y `admin`) registra tutor y mascota en una sola transacción. | `RegisterOwnerWithPatientAction` | `it('BR-07 registers owner and patient in one step for reception')` |
| BR-08 | Si el tutor tiene email, se crea su usuario `owner` y se envía el link para definir contraseña (expira en ⚙horas) **solo después del commit**. Sin email, el tutor existe igual pero sin cuenta. | `RegisterOwnerAction` | `it('BR-08 sends set-password link only for owners with email and only after commit')` |
| BR-09 | Cada mascota pertenece a un solo tutor (FK `NOT NULL`, `restrict`). El microchip, si existe, tiene 15 dígitos y es único. `birthdate ≤ hoy`. | `RegisterPatientAction` → `InvalidMicrochipException` | `it('BR-09 rejects microchip that is not 15 numeric digits')` |

## M2 · Agenda (zona roja)
| ID | Invariante | Action → Excepción | Test RED |
|---|---|---|---|
| BR-10 | No pueden existir dos citas del mismo `doctor_id` con `period` solapado y `status != 'cancelled'` (`EXCLUDE USING gist`). | `BookAppointmentAction` captura `23P01` → `SlotUnavailableException` | `it('BR-10 rejects overlapping appointment for same doctor')` |
| BR-11 | De dos reservas simultáneas al mismo slot, exactamente una tiene éxito (se prueba con dos conexiones). | `BookAppointmentAction` | `it('BR-11 lets only one of two concurrent bookings succeed')` |
| BR-12 | Médicos distintos pueden tener citas en el mismo `period`. | `BookAppointmentAction` | `it('BR-12 allows the same slot for different doctors')` |
| BR-13 | La cita debe caer completa dentro de un bloque activo de `DoctorSchedule` y fuera de las ausencias del médico. | `BookAppointmentAction` → `OutsideDoctorScheduleException` | `it('BR-13 rejects appointment outside doctor schedule')` |
| BR-14 | `start > now`. Portal y bot exigen además ⚙horas de antelación (recepción está exenta). La duración sale del servicio. | `BookAppointmentAction` → `InvalidAppointmentTimeException` | `it('BR-14 rejects booking in the past')` |
| BR-15 | Con "primer médico disponible", se prueba con cada médico habilitado para el servicio y se reintenta ante `23P01`. Si ninguno tiene el slot → `SlotUnavailableException`. | `BookAppointmentAction` | `it('BR-15 assigns first available doctor when none is chosen')` |
| BR-16 | Cancelar libera el slot de inmediato. El tutor solo cancela sus propias citas y con ⚙horas de antelación. | `CancelAppointmentAction` → `CancellationWindowClosedException` | `it('BR-16 frees the slot when an appointment is cancelled')` |
| BR-17 | Web, bot y recepción reservan con la misma Action y registran el `channel`. | Arch test | `arch('BR-17 every channel books through BookAppointmentAction')` |

## M3 · Sala de espera
| ID | Invariante | Action → Excepción | Test RED |
|---|---|---|---|
| BR-18 | Una mascota tiene como máximo una entrada activa (`waiting`, `in_consultation`), con UNIQUE parcial. Si llega con cita, la cita pasa a `checked_in`. | `CheckInPatientAction` → `PatientAlreadyInWaitingRoomException` | `it('BR-18 rejects a second active entry for the same patient')` |
| BR-19 | ⚠ DEC-01. Toda entrada exige `service_type`, `priority ∈ {1,2,3}` y responsable: `doctor_id` para servicios clínicos y el peluquero para peluquería. | `CheckInPatientAction` → `ValidationException` | `it('BR-19 requires a doctor for clinical services and a groomer for grooming')` |
| BR-20 | La fila se ordena por `priority DESC, checked_in_at ASC`. | `Queries\WaitingRoom\DoctorQueueQuery` | `it('BR-20 orders queue by priority desc then arrival asc')` |
| BR-21 | Transiciones válidas: `waiting → in_consultation → completed` y `waiting → cancelled \| no_show`. Cualquier otra se rechaza. | `TransitionWaitingEntryAction` → `InvalidStateTransitionException` | `it('BR-21 rejects transition from completed back to waiting')` |
| BR-22 | Solo el médico asignado llama al paciente. Un médico tiene como máximo una entrada `in_consultation` (UNIQUE parcial). | `CallPatientAction` → `DoctorBusyException` | `it('BR-22 prevents a doctor from having two patients in consultation')` |
| BR-23 | Al completar la atención se generan los cargos pendientes (servicios e insumos) para que recepción cobre. | `CompleteAttentionAction` | `it('BR-23 creates pending charges when attention is completed')` |

## M4 · Clínica, documentos y peluquería
| ID | Invariante | Action → Excepción | Test RED |
|---|---|---|---|
| BR-24 | Las constantes vitales deben estar en rango: `weight_kg > 0`, temperatura entre 30 y 45 °C, FC y FR > 0. | `RecordConsultationAction` → `ValidationException` | `it('BR-24 rejects vital signs outside physiological bounds')` |
| BR-25 | Una consulta cerrada es inmutable. Solo se corrige con un addendum (autor y timestamp), que queda auditado. | `UpdateConsultationAction` → `ClinicalRecordLockedException` | `it('BR-25 rejects editing a closed consultation')` |
| BR-26 | Vacuna y desparasitación exigen `batch_id`, y el lote no puede estar vencido en `applied_at`. | `ApplyVaccinationAction` → `ExpiredBatchException` | `it('BR-26 rejects applying a vaccine from an expired batch')` |
| BR-27 | `next_due_at = applied_at + product.booster_interval_days`. Si el producto no define intervalo, queda `null` y no hay recordatorio. | `ApplyVaccinationAction` | `it('BR-27 calculates next booster date from product interval')` |
| BR-28 | Los adjuntos se aceptan en `pdf/jpeg/png/webp` y hasta ⚙MB, en disco privado. Se descargan solo con URL firmada temporal y pasando la Policy. | `AttachClinicalFileAction` → `DisallowedFileException` | `it('BR-28 rejects attachment with disallowed mime type')` |
| BR-29 | El récipe exige médico con `license_number` y al menos una línea RX + indicación. El PDF va en 2 columnas (RX \| INDICACIONES) con marca de agua. Una vez emitido es correlativo e inmutable. | `IssuePrescriptionAction` → `MissingDoctorLicenseException` | `it('BR-29 refuses to issue a prescription without doctor license number')` |
| BR-30 | Cada sesión de peluquería guarda notas de piel/pelaje y de comportamiento. Al iniciar una sesión nueva se muestran las notas de comportamiento de la anterior. | `StartGroomingSessionAction` | `it('BR-30 exposes last behavior notes when a new grooming session starts')` |

## M5 · Viajes INSAI
> Las vigencias y ventanas por destino viven en `travel_requirements` (JSONB), que edita la clínica. Nunca se escriben en el código.

| ID | Invariante | Action → Excepción | Test RED |
|---|---|---|---|
| BR-31 | El microchip ISO (15 dígitos) debe tener `implanted_at ≤ applied_at` de la antirrábica considerada. | `ValidateTravelDossierAction` → `MicrochipAfterRabiesException` | `it('BR-31 rejects dossier when microchip was implanted after rabies vaccination')` |
| BR-32 | ⚠ DEC-02. La mascota tiene más de 3 meses de edad en la fecha que defina DEC-02. | `ValidateTravelDossierAction` → `PatientTooYoungException` | `it('BR-32 rejects patient younger than 3 months')` |
| BR-33 | La antirrábica sigue vigente en `travel_date` y cumple los ⚙días mínimos de espera del destino. | `ValidateTravelDossierAction` → `RequirementNotMetException` | `it('BR-33 flags rabies vaccine expired at travel date')` |
| BR-34 | La desparasitación interna y externa cae dentro de la ventana del destino. | `ValidateTravelDossierAction` → `RequirementNotMetException` | `it('BR-34 flags deworming outside destination window')` |
| BR-35 | Estados `draft → ready → submitted → approved \| rejected`. Solo pasa a `ready` si se cumplen BR-31 a BR-34. | `MarkDossierReadyAction` → `RequirementNotMetException` | `it('BR-35 blocks marking dossier ready while any requirement fails')` |

## M6 · Caja bimoneda (zona roja)
| ID | Invariante | Action → Excepción | Test RED |
|---|---|---|---|
| BR-36 | No se emite ni se cobra sin una `CashSession` abierta del usuario, y cada usuario tiene como máximo una sesión abierta (UNIQUE parcial). | `IssueInvoiceAction` → `NoOpenCashSessionException` | `it('BR-36 rejects charging without an open cash session')` |
| BR-37 | ⚠ DEC-03. La tasa vigente es la de `effective_date` más reciente `≤ hoy`. Si es más antigua que ⚙`max_rate_age_days`, no se emite. | `IssueInvoiceAction` → `MissingExchangeRateException` | `it('BR-37 refuses to issue invoice when exchange rate is stale')` |
| BR-38 | Al emitir se congelan, por línea, `exchange_rate_bcv`, `amount_usd` y `amount_ves = round_half_up(usd × rate, 2)`. Los totales son la suma de las líneas y nunca se recalculan. | `IssueInvoiceAction` | `it('BR-38 freezes usd ves and rate on issued invoice')` |
| BR-39 | Una factura emitida es inmutable. Anularla exige `invoices.void` y un motivo, y revierte el stock (BR-46) y las comisiones (BR-49). | `VoidInvoiceAction` → `InvoiceLockedException` | `it('BR-39 rejects modifying an issued invoice')` |
| BR-40 | ⚠ DEC-07. La factura pasa a `paid` solo si los pagos, convertidos con la tasa congelada, cubren el total. Si no, queda `partially_paid`. | `RegisterPaymentAction` | `it('BR-40 marks invoice paid only when payments cover total')` |
| BR-41 | Los egresos congelan los mismos 3 campos y exigen `expenses.manage`. | `RecordExpenseAction` | `it('BR-41 freezes exchange rate on expenses')` |
| BR-42 | Al cerrar la caja se calcula el esperado por método y moneda, y se guardan el monto contado y la diferencia. Una sesión cerrada no acepta movimientos. | `CloseCashSessionAction` → `CashSessionClosedException` | `it('BR-42 computes expected vs counted difference per currency on close')` |

## M7 · Inventario por lotes (zona roja)
| ID | Invariante | Action → Excepción | Test RED |
|---|---|---|---|
| BR-43 | Al emitir se descuenta por FEFO: lotes no vencidos por `expires_at ASC` con `FOR UPDATE`, pudiendo usar varios lotes. Si no alcanza el stock, se revierte toda la factura. `CHECK (quantity >= 0)` | `DeductStockFefoAction` → `InsufficientStockException` | `it('BR-43 deducts stock from earliest expiring batch first')` |
| BR-44 | Los lotes vencidos nunca se descuentan. | `DeductStockFefoAction` | `it('BR-44 skips expired batches during fefo deduction')` |
| BR-45 | La línea de una vacunación con lote registrado (BR-26) descuenta ese lote y no aplica FEFO. Nunca hay doble descuento. | `DeductStockFefoAction` | `it('BR-45 deducts the recorded batch for vaccination lines instead of fefo')` |
| BR-46 | Todo cambio de stock genera un `StockMovement` (tipo, usuario, referencia y motivo) y `batch.quantity` es igual a la suma de sus movimientos. Los ajustes exigen `inventory.adjust`. | `AdjustStockAction` | `it('BR-46 records a stock movement for every quantity change')` |
| BR-47 | Se genera una alerta si el stock no vencido es ≤ `min_stock`, o si un lote vence en ≤ ⚙días. | `Queries\Inventory\StockAlertsQuery` | `it('BR-47 lists products at or below minimum stock')` |

## M8 · Trabajadores y nómina
| ID | Invariante | Action → Excepción | Test RED |
|---|---|---|---|
| BR-48 | `base_salary_usd ≥ 0`. La comisión (porcentaje o monto fijo) se define por empleado y tipo de servicio, y exige `payroll.manage`. | `UpsertEmployeeAction` → `AuthorizationException` | `it('BR-48 forbids setting salaries without payroll.manage')` |
| BR-49 | ⚠ DEC-06. La comisión se calcula y se congela por cada línea con `provider_employee_id`. Si la factura se anula, la comisión se revierte. | `AccrueCommissionsAction` | `it('BR-49 creates commission for provider when invoice is paid')` |
| BR-50 | ⚠ DEC-08. Un empleado no puede tener dos períodos de nómina solapados (`EXCLUDE` sobre `daterange`). Un período cerrado es inmutable. | `ClosePayrollAction` → `OverlappingPayrollException` | `it('BR-50 rejects overlapping payroll periods for the same employee')` |

## M9 · WhatsApp
| ID | Invariante | Action → Excepción | Test RED |
|---|---|---|---|
| BR-51 | ⚠ DEC-05. `available_at = max(now, último available_at) + random_int(8,15)s`, calculado bajo `pg_advisory_xact_lock`. El encolado se hace después del commit. | `EnqueueWhatsAppMessageAction` | `it('BR-51 spaces consecutive messages between 8 and 15 seconds')` |
| BR-52 | Idempotencia con `UNIQUE (kind, subject_type, subject_id, scheduled_for::date)`. | `EnqueueWhatsAppMessageAction` | `it('BR-52 does not enqueue duplicate reminder for the same subject')` |
| BR-53 | Las citas se recuerdan el día anterior a la ⚙hora configurada, y las vacunas y desparasitaciones ⚙días antes de `next_due_at`. Al enviar se revalida: no sale si la cita se canceló o hay `opt_out`. | `SendWhatsAppMessageAction` | `it('BR-53 skips reminder when appointment was cancelled before sending')` |
| BR-54 | Fuera del ⚙horario permitido (TZ de la clínica) no se envía: el mensaje se reprograma al inicio de la siguiente ventana. | `SendWhatsAppMessageAction` | `it('BR-54 defers messages scheduled outside allowed hours')` |
| BR-55 | ⚠ DEC-05. El webhook valida la firma o el token del proveedor antes de procesar. Si es inválido responde 403 y no persiste nada. | `HandleWhatsAppWebhookAction` → `InvalidWebhookSignatureException` | `it('BR-55 rejects webhook with invalid signature')` |

---

## Decisiones pendientes (las responde la clínica; se anota "Resuelta: X, fecha")
| DEC | Afecta | Duda | Opciones y consecuencias | Recomendación |
|---|---|---|---|---|
| DEC-01 | BR-19, BR-30 | ¿El peluquero usa el sistema? | **A)** Empleado sin acceso: recepción registra las notas. **B)** Usuario con permisos propios y su propia fila. | A en F1; B si el peluquero debe escribir sus propias notas. |
| DEC-02 | BR-32 | ¿En qué fecha se miden los 3 meses de edad? | **A)** A la antirrábica. **B)** Al viaje. **C)** Ambas. | C, confirmándolo con los requisitos INSAI vigentes. |
| DEC-03 | BR-37 | ¿Qué tasa se usa en fines de semana y feriados? | **A)** La última, si tiene ≤ N días. **B)** Siempre la última. **C)** Exigir carga diaria. | A con N = 3. |
| DEC-04 | BR-58 | ¿De dónde sale la tasa automática? | **A)** Web del BCV (frágil si cambia la página). **B)** API de terceros (dependencia externa). **C)** Solo carga manual. | A o B, siempre con C como respaldo. |
| DEC-05 | BR-51, BR-55 | ¿Qué proveedor de WhatsApp se usa? | **A)** Cloud API oficial de Meta: plantillas aprobadas, costo por conversación, sin riesgo de baneo. **B)** API no oficial: gratis, con riesgo de baneo. El escalonado de 8–15 s solo tiene sentido con B. | Definir antes de F5. |
| DEC-06 | BR-49 | ¿Cuándo se gana la comisión y sobre qué base? | **A)** Al quedar pagada. **B)** Al emitir. Base: el monto USD de la línea. | A. |
| DEC-07 | BR-40 | ¿Cómo se maneja el vuelto? | **A)** En la moneda que pida el cliente, como salida de caja por moneda. **B)** Solo en la moneda del pago. | A. |
| DEC-08 | BR-50 | ¿Cada cuánto se paga la nómina? | **A)** Quincenal. **B)** Mensual. **C)** Configurable. | C, con quincenal por defecto. |
| DEC-09 | BR-59 | ¿Cómo recibe su acceso el personal nuevo? | **A)** El actor fija la contraseña. **B)** Link para definir la contraseña, después del commit. | **Resuelta: B, 2026-11-09.** Pasa a ser BR-59. |
| DEC-10 | BR-01 | ¿Cuántos roles tiene el personal y cuáles asigna quien solo tiene `staff.manage`? | **A)** Un rol por persona. **B)** Varios roles. | **Resuelta: B, con `owner` nunca combinado con roles de staff, 2026-11-09.** Pendiente de confirmar con la clínica. |
| DEC-11 | BR-01 | ¿Editar o degradar a un admin existente exige `staff.manage_admins`? | **A)** Sí. **B)** No, solo al asignar el rol. | **Resuelta: A, 2026-11-09.** Amplía BR-01. |
