# 📐 Guía Rápida: Convenciones REST e Inyección en Laravel

Esta guía sirve como "Cheat Sheet" (acordeón) para recordar la correspondencia entre los métodos HTTP del Frontend, los métodos estándar de los controladores de Laravel y cómo viajan los parámetros.

---

## 📊 Tabla de Convenciones REST (Controladores Estándar)

| Nombre del Método | Método HTTP (Frontend) | Propósito Conceptual | ¿Qué ejecuta dentro en la BD? |
| :--- | :---: | :--- | :--- |
| **`index`** | `GET` | Listar múltiples registros | `Model::get()` o `Model::paginate()` (SELECT) |
| **`show`** | `GET` | Ver un registro individual | `Model::find($id)` (SELECT de una fila) |
| **`store`** | `POST` | Crear/guardar un nuevo registro | `Model::create([...])` (**INSERT** en SQL) |
| **`update`** | `PUT` / `PATCH` | Modificar un registro existente | `$model->update([...])` (**UPDATE** en SQL) |
| **`destroy`** | `DELETE` | Eliminar físicamente un registro | `$model->delete()` (**DELETE** en SQL) |

---

## 💡 El Secreto de los Parámetros del Controlador

Para saber qué colocar dentro de los paréntesis de tus funciones (`public function ...`), sigue estas dos reglas:

### Regla 1: ¿Vienen datos del Body (Formulario/JSON)?
* Si el frontend envía datos (POST/PATCH), **SIEMPRE** va la petición de validación primero:
  `StoreUserRequest $request` o `UpdateClubRequest $request`.

### Regla 2: ¿Hay variables dinámicas en la URL de la ruta?
* Mira la definición de la ruta. Si tiene llaves `{un_modelo}`, debes inyectarlo con el mismo nombre exacto en los parámetros de la función.

---

## 🛠️ Ejemplos Prácticos de Inyección de Parámetros

### Caso A: Sin variables en la URL
* **URL:** `POST /api/auth/register` (Registrar usuario)
* **Parámetros:**
  ```php
  public function register(StoreUserRequest $request)
  ```
  *(No hay `{uuid}` en la URL, solo capturas los datos enviados).*

### Caso B: Con variable de identificación única
* **URL:** `PATCH /api/clubs/{club}` (Actualizar datos del Club)
* **Parámetros:**
  ```php
  public function update(UpdateClubRequest $request, Club $club)
  ```
  *(Capturas los nuevos datos con `$request` y Laravel busca automáticamente el `$club` de la URL).*

### Caso C: Acciones anidadas (Dos modelos)
* **URL:** `POST /api/clubs/{club}/channels` (Crear canal dentro de un club)
* **Parámetros:**
  ```php
  public function store(StoreChannelRequest $request, Club $club)
  ```
  *(Capturas los datos del nuevo canal y asocias su creación al `$club` inyectado).*
