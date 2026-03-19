# Gap Analysis + Propuesta de Refactor de Pagos

Fecha: `2026-03-18`

Fuentes analizadas:
- `/Users/josueramos/Downloads/Estructura de 8 Tablas propuesta. 16-03-2026.docx`
- `/Users/josueramos/Downloads/ERM propuesto con 8 tablas.pptx`

Nota:
- El `.docx` sí contiene contenido útil.
- El `.pptx` extraíble no aporta un ERM legible adicional; parece estar basado en layout maestro y no agrega detalle estructural accionable.

---

## 1. Conclusión Ejecutiva

La estructura actual del sistema SGAON es superior a la propuesta simplificada de `8 tablas`.

No se recomienda adaptar el sistema al modelo del documento.  
Sí se recomienda tomar dos necesidades de negocio implícitas en esa propuesta:

1. Mejor trazabilidad entre operación académica y finanzas.
2. Eventual módulo formal de calificaciones/notas.

El foco inmediato debe estar en `pagos/cargos`, no en simplificar el ERM.

---

## 2. Gap Analysis Tabular

| Área | Propuesta documentos | Estructura actual | Evaluación | Acción recomendada |
|---|---|---|---|---|
| Estudiantes | `id_representante` directo en estudiante | `students` + `representatives` + pivot `student_representative` | Actual mejor | Mantener |
| Representantes | Relación 1:N implícita | Relación N:M | Actual mejor | Mantener |
| Profesores | Tabla simple | Tabla simple + relaciones | Correcto | Mantener |
| Cursos | Curso con profesor | `courses` operativo con profesor/periodo/horario/fechas/horas | Actual mejor | Mantener |
| Grupos | Mezcla profesor + estudiante + fecha en una sola tabla | `groups` separado de `enrollments` y `class_sessions` | Actual mejor | Mantener |
| Horarios | Horario por profesor | `schedule_templates` reutilizable | Actual mejor | Mantener |
| Sesiones | No claramente separadas | `class_sessions` explícita | Actual mejor | Mantener |
| Inscripciones | Implícitas | `enrollments` explícita | Actual mejor | Mantener |
| Asistencia | No normalizada | `attendance_records` | Actual mejor | Mantener |
| Notas | Existe tabla `Notas` | No existe módulo formal | Gap real | Evaluar módulo `grades` |
| Pagos | Una tabla `pagos` | `charges` + `payments` + `receipts` | Actual mejor conceptualmente | Fortalecer |
| Vinculación pago-curso | Pago asociado a grupo/asignatura | Hoy sin FK académica fuerte en `charges` | Gap real | Agregar `enrollment_id` |
| Pago múltiple | No resuelto | `payment -> charge` único | Limitación actual | Evaluar `payment_allocations` |
| Estado financiero | Estado en pagos | Estado principal vive en cargos | Válido | Mantener y complementar |

---

## 3. Diagnóstico de la Estructura Actual de Pagos

Estado actual:

### `charges`
- `campus_id`
- `student_id`
- `concept`
- `amount`
- `due_date`
- `status`
- `notes`

### `payments`
- `campus_id`
- `student_id`
- `charge_id` nullable
- `amount`
- `paid_at`
- `method`
- `reference`
- `notes`

### `receipts`
- `campus_id`
- `payment_id`
- `receipt_number`
- `issued_at`

### Fortalezas actuales

1. Existe separación correcta entre:
- deuda (`charges`)
- dinero recibido (`payments`)
- comprobante (`receipts`)

2. Soporta:
- pago parcial
- conciliación
- recibo por pago

### Debilidades actuales

1. `charges` no está ligado de forma fuerte a la unidad académica.
- No responde bien:
  - qué curso se está cobrando
  - qué inscripción origina la deuda
  - qué grupo/período corresponde

2. `payments` solo se vincula a:
- `student_id`
- `charge_id`

3. No existe soporte explícito para:
- un pago que cubra varios cargos

4. No hay estado operativo en `payments` para flujos tipo:
- validación bancaria
- reverso
- anulación

---

## 4. Propuesta Exacta de Cambios de BD Para Pagos

## 4.1 Objetivo del refactor

Cada cargo y cada pago debe poder responder:

1. Qué alumno paga.
2. Qué inscripción origina la deuda.
3. Qué curso / grupo / período se está cobrando.
4. Cuánto ha sido aplicado realmente.
5. Cuál es el saldo pendiente.
6. Si un pago cubrió uno o varios cargos.

---

## 4.2 Estrategia recomendada

No reemplazar tablas existentes.  
Extender el modelo actual de forma no destructiva.

### Fase A
Amarrar `charges` a `enrollments`.

### Fase B
Agregar soporte de aplicación múltiple si negocio lo necesita.

---

## 4.3 Cambios exactos propuestos

## Tabla `charges`

Agregar columnas:

- `enrollment_id` nullable FK -> `enrollments`
- `course_id` nullable FK -> `courses`
- `group_id` nullable FK -> `groups`
- `period_id` nullable FK -> `periods`
- `charge_type` string(50) nullable
- `billing_period_label` string(60) nullable
- `origin` string(40) nullable
- `voided_at` datetime nullable

### Criterio

- `enrollment_id` será la referencia principal.
- `course_id`, `group_id`, `period_id` pueden usarse como denormalización de lectura rápida.

### Valores sugeridos

`charge_type`:
- `tuition`
- `materials`
- `registration`
- `makeup`
- `other`

`origin`:
- `manual`
- `recurring_job`
- `migration`
- `legacy_unlinked`

---

## Tabla `payments`

Agregar columnas:

- `status` string(30) default `confirmed`
- `received_by` nullable FK -> `users`
- `voided_at` datetime nullable
- `paid_at_datetime` datetime nullable

### Valores sugeridos `status`
- `confirmed`
- `pending_validation`
- `voided`
- `reversed`

Nota:
- `paid_at` puede mantenerse por compatibilidad.
- `paid_at_datetime` serviría si hace falta mayor precisión horaria.

---

## Nueva tabla `payment_allocations`

Usar esta tabla si negocio requiere que un pago cubra varios cargos.

Campos:

- `id`
- `payment_id` FK -> `payments`
- `charge_id` FK -> `charges`
- `amount_applied` decimal(10,2)
- `created_at`
- `updated_at`

### Regla

La suma de `payment_allocations.amount_applied` por pago debe coincidir con `payments.amount`.

### Ventaja

Permite casos reales:
- un pago cubre mensualidad + material
- un pago cubre varios saldos viejos
- un pago se distribuye entre cargos parciales

---

## 4.4 Migraciones Laravel exactas propuestas

## Migración 1: extender `charges`

Archivo sugerido:
- `database/migrations/2026_03_18_140000_add_academic_context_to_charges_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table): void {
            $table->foreignId('enrollment_id')->nullable()->after('student_id')->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->after('enrollment_id')->constrained()->nullOnDelete();
            $table->foreignId('group_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->foreignId('period_id')->nullable()->after('group_id')->constrained('periods')->nullOnDelete();
            $table->string('charge_type', 50)->nullable()->after('concept');
            $table->string('billing_period_label', 60)->nullable()->after('charge_type');
            $table->string('origin', 40)->nullable()->after('billing_period_label');
            $table->dateTime('voided_at')->nullable()->after('notes');

            $table->index(['student_id', 'status', 'due_date']);
            $table->index(['enrollment_id']);
            $table->index(['course_id', 'group_id', 'period_id']);
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table): void {
            $table->dropIndex(['student_id', 'status', 'due_date']);
            $table->dropIndex(['enrollment_id']);
            $table->dropIndex(['course_id', 'group_id', 'period_id']);
            $table->dropConstrainedForeignId('period_id');
            $table->dropConstrainedForeignId('group_id');
            $table->dropConstrainedForeignId('course_id');
            $table->dropConstrainedForeignId('enrollment_id');
            $table->dropColumn(['charge_type', 'billing_period_label', 'origin', 'voided_at']);
        });
    }
};
```

## Migración 2: extender `payments`

Archivo sugerido:
- `database/migrations/2026_03_18_140100_add_operational_fields_to_payments_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('status', 30)->default('confirmed')->after('reference');
            $table->foreignId('received_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at_datetime')->nullable()->after('paid_at');
            $table->dateTime('voided_at')->nullable()->after('notes');

            $table->index(['student_id', 'paid_at']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['student_id', 'paid_at']);
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('received_by');
            $table->dropColumn(['status', 'paid_at_datetime', 'voided_at']);
        });
    }
};
```

## Migración 3: crear `payment_allocations`

Archivo sugerido:
- `database/migrations/2026_03_18_140200_create_payment_allocations_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_applied', 10, 2);
            $table->timestamps();

            $table->index(['payment_id']);
            $table->index(['charge_id']);
            $table->unique(['payment_id', 'charge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
```

---

## 5. Orden Recomendado de Migraciones sin Romper lo Actual

## Etapa 1: Extensión no destructiva

Ejecutar:

1. `add_academic_context_to_charges_table`
2. `add_operational_fields_to_payments_table`

Esto no rompe ni UI ni controladores actuales.

---

## Etapa 2: Backfill histórico

Crear un comando de backfill.

Archivo sugerido:
- `app/Console/Commands/BackfillChargeAcademicContext.php`

Objetivo:
- rellenar `enrollment_id`, `course_id`, `group_id`, `period_id`

### Regla de backfill recomendada

Para cada `charge`:

1. Buscar inscripción activa del alumno.
2. Si no existe, buscar la más reciente por `enrolled_at` o `created_at`.
3. Si existe match confiable:
   - `enrollment_id = enrollment.id`
   - `group_id = enrollment.group_id`
   - `course_id = enrollment.group.course_id`
   - `period_id = course.period_id` o `group.period` si no existe FK
   - `origin = migration`
4. Si no hay match:
   - dejar FK null
   - `origin = legacy_unlinked`

### Pseudocódigo

```php
Charge::query()->with('student')->chunkById(200, function ($charges) {
    foreach ($charges as $charge) {
        $enrollment = Enrollment::query()
            ->where('student_id', $charge->student_id)
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('enrolled_at')
            ->first();

        if (! $enrollment) {
            $charge->update([
                'origin' => 'legacy_unlinked',
            ]);
            continue;
        }

        $charge->update([
            'enrollment_id' => $enrollment->id,
            'group_id' => $enrollment->group_id,
            'course_id' => $enrollment->group?->course_id,
            'period_id' => $enrollment->group?->course?->period_id,
            'origin' => 'migration',
        ]);
    }
});
```

---

## Etapa 3: Ajuste del código sin romper compatibilidad

### `FinanceController`

Archivo actual:
- `/Users/josueramos/LOCALPROJECTS/WEBS/OnEnglish/sgaon/app/Http/Controllers/FinanceController.php`

### Cambios recomendados

## `storeCharge`

Agregar validación:
- `enrollment_id` nullable o required según etapa
- `charge_type`
- `billing_period_label`

Lógica:
1. Si llega `enrollment_id`, derivar:
   - `student_id`
   - `group_id`
   - `course_id`
   - `period_id`
2. No permitir inconsistencias entre `student_id` y `enrollment.student_id`

### Validación sugerida

```php
'enrollment_id' => ['nullable', 'exists:enrollments,id'],
'charge_type' => ['nullable', 'in:tuition,materials,registration,makeup,other'],
'billing_period_label' => ['nullable', 'string', 'max:60'],
```

### Reglas sugeridas

```php
if (! empty($data['enrollment_id'])) {
    $enrollment = Enrollment::with('group.course')->findOrFail($data['enrollment_id']);
    $data['student_id'] = $enrollment->student_id;
    $data['group_id'] = $enrollment->group_id;
    $data['course_id'] = $enrollment->group?->course_id;
    $data['period_id'] = $enrollment->group?->course?->period_id;
}
```

## `storePayment`

Etapa mínima:
- seguir usando `charge_id`
- agregar:
  - `status = confirmed`
  - `received_by = auth()->id()`

Etapa avanzada con allocations:
1. crear `payment`
2. crear una o varias filas en `payment_allocations`
3. reconciliar todos los cargos impactados

---

## Etapa 4: Introducir `payment_allocations`

Solo cuando el negocio lo necesite.

### No hacer todavía si:
- cada pago cubre un solo cargo
- no hay casos de pago mixto

### Sí hacerlo si:
- un pago puede liquidar varias deudas
- se requiere conciliación bancaria más fina
- se quieren recibos que cubran múltiples conceptos

---

## Etapa 5: Deprecación controlada

Cuando `payment_allocations` ya esté estable:

1. Mantener `payments.charge_id` como legado durante una etapa.
2. Hacer que nueva lógica lea primero `payment_allocations`.
3. Luego decidir si `payments.charge_id`:
   - se elimina
   - o queda nullable como compatibilidad histórica

No conviene quitar `charge_id` en la primera iteración.

---

## 6. Impacto en Reconciliación

Archivo actual:
- `/Users/josueramos/LOCALPROJECTS/WEBS/OnEnglish/sgaon/app/Support/FinanceReconcile.php`

### Estado actual

```php
$paidTotal = (float) $charge->payments()->sum('amount');
```

Esto funciona solo si el pago está directamente atado a un cargo.

### Evolución recomendada

## Etapa actual
Mantenerlo así.

## Etapa con allocations
Cambiar a:

```php
$paidTotal = (float) $charge->paymentAllocations()->sum('amount_applied');
```

o un fallback mixto mientras migra:

```php
$paidTotal = $charge->paymentAllocations()->exists()
    ? (float) $charge->paymentAllocations()->sum('amount_applied')
    : (float) $charge->payments()->sum('amount');
```

---

## 7. Impacto en Reportes

Archivo actual de reporte:
- `/Users/josueramos/LOCALPROJECTS/WEBS/OnEnglish/sgaon/resources/views/reports/payments.blade.php`

### Recomendaciones

Agregar columnas en reportes:
- curso
- grupo
- período
- tipo de cargo
- origen

### Nuevo valor para negocio

El reporte debería poder responder:
- cuánto debe un alumno por curso
- cuánto debe por período
- cuánto está vencido por inscripción

### Filtros recomendados

En `reports.payments`:
- `course_id`
- `group_id`
- `period_id`
- `charge_type`
- `origin`

---

## 8. Impacto en Recibos

Estructura actual:
- `receipts` depende de `payment`

Eso está bien y debe mantenerse.

### Recomendación

En la plantilla o metadata del recibo mostrar:
- alumno
- curso o grupo si el pago está aplicado a un cargo académico
- período
- conceptos cubiertos

Si implementas `payment_allocations`, un recibo podría listar:
- mensualidad marzo
- material didáctico
- reinscripción

---

## 9. Qué no cambiar

No recomiendo:

1. poner `id_representante` directo en `students`
2. meter `id_estudiante` dentro de `groups`
3. colapsar `charges` y `payments` en una sola tabla
4. eliminar `receipts`
5. simplificar el ERM actual a 8 tablas

Eso degradaría la capacidad operativa del sistema.

---

## 10. Recomendación de implementación por prioridad

## Prioridad alta

1. `charges.enrollment_id`
2. `charges.course_id`
3. `charges.group_id`
4. `charges.period_id`
5. backfill histórico

## Prioridad media

6. `payments.status`
7. `payments.received_by`
8. filtros/reportes por contexto académico

## Prioridad media-alta

9. `payment_allocations` si negocio maneja pagos multipropósito

## Prioridad futura

10. módulo de `grades` / `notas`

---

## 11. Decisión recomendada

Si el objetivo inmediato es mejorar finanzas sin romper lo actual:

1. implementar primero la extensión de `charges`
2. hacer backfill
3. adaptar `FinanceController`
4. ajustar reportes
5. dejar `payment_allocations` para una segunda etapa

Esa es la ruta más segura y con mejor retorno.
