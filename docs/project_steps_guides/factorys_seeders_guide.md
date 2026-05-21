# Laravel 13 Architecture Guide: Factories & Database Seeding (UUID-Ready)

Este documento guía el proceso de automatización para la inyección de datos de prueba en la base de datos PostgreSQL de Vyntra. Dado que el sistema utiliza llaves primarias basadas en UUID controladas por el método `boot()` de los modelos, las fábricas NO deben intentar generar ni forzar el campo `uuid`.

---

## 1. Mapeo de Archivos a Crear (Checklist de Ejecución)
Debes generar un archivo Factory para cada una de las entidades principales antes de configurar el Seeder maestro.

### Factories en `database/factories/`:
- [x] `UserFactory.php` (Modificar la que viene por defecto)
- [x] `ClubFactory.php`
- [x] `ClubChannelFactory.php`
- [x] `ChannelMessageFactory.php`

### Seeders en `database/seeders/`:
- [x] `DatabaseSeeder.php` (El director de orquesta central)

---

## 2. Instrucciones de Construcción para la IA y el Desarrollador

### PASO 1: Configurar las Factories (Generadores de Datos Falsos)
Las fábricas definen el blueprint de cómo inventar registros usando la librería nativa `fake()`. 

#### Reglas Estrictas para las Factories:
1. **Omitir la Primary Key (`uuid`):** El modelo de Eloquent maneja la creación del UUID automáticamente en su evento `creating`. Forzarlo en la factory rompería la abstracción del modelo.
2. **Uso de Llaves Foráneas:** Para relacionar tablas (ej: asociar un canal a un club), se debe pasar la instancia del modelo padre directamente en el atributo (`'club_uuid' => Club::factory()`). Laravel se encargará de resolver el UUID del padre e inyectarlo en el hijo.

#### Estructura del código esperado en las Factories:

```php
// Ejemplo para ClubFactory.php
namespace Database\Factories;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClubFactory extends Factory
{
    protected $model = Club::class;

    public function definition(): array
    {
        return [
            // OMITIDO: 'uuid' -> Lo maneja el boot() del Modelo
            'name' => fake()->unique()->company(),
            'description' => fake()->sentence(),
            'avatar_url' => fake()->imageUrl(200, 200, 'abstract'),
            'cover_image_url' => fake()->imageUrl(800, 400, 'nature'),
            'category_tag' => fake()->randomElement(['gaming', 'programming', 'music', 'anime']),
            
            // RELACIÓN HISTÓRICA: Crea un usuario automáticamente para que sea el dueño de este club
            'owner_uuid' => User::factory(), 
        ];
    }
}