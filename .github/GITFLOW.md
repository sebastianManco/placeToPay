# Estándar GitFlow - Proyecto PlaceToPay

Este repositorio sigue el modelo de ramificación **GitFlow** para mantener un ciclo de vida ordenado, predecible y seguro para el software.

---

## 🏛️ Ramas principales (Permanentes)

1. **`master` (Producción)**
   - Contiene el código en estado de producción estable.
   - Cada commit en `master` representa una versión liberada y debe estar etiquetado con un tag de versión (`v1.0.0`).
   - **Nunca se hace commit directo a `master`**.

2. **`develop` (Integración)**
   - Es la rama central de desarrollo donde confluyen todas las nuevas funcionalidades.
   - Refleja el estado del próximo lanzamiento planeado.

---

## 🚀 Ramas de soporte (Temporales)

| Tipo de rama | Rama origen | Rama destino | Nomenclatura | Propósito |
| :--- | :--- | :--- | :--- | :--- |
| **Feature** | `develop` | `develop` | `feature/<nombre-tarea>` | Desarrollo de una nueva funcionalidad o configuración. |
| **Release** | `develop` | `master` y `develop` | `release/<version>` | Congelamiento de código, pruebas finales y corrección de detalles antes de salir a producción. |
| **Hotfix** | `master` | `master` y `develop` | `hotfix/<version>` | Corrección urgente de un error crítico detectado en producción. |

---

## 🔄 Flujo de trabajo para desarrolladores

### 1. Iniciar una nueva tarea (Feature)
```bash
# 1. Asegurar que develop esté actualizada
git checkout develop
git pull origin develop

# 2. Crear la rama de la funcionalidad
git checkout -b feature/mi-nueva-funcionalidad
```

### 2. Guardar el trabajo con Conventional Commits
Utilizar mensajes claros bajo la convención:
- `feat: ...` para nuevas funcionalidades
- `fix: ...` para corrección de bugs
- `docs: ...` para cambios en documentación
- `style: ...` formato y estilos de código
- `refactor: ...` refactorización sin cambio funcional
- `test: ...` adición o ajuste de pruebas
- `chore: ...` tareas auxiliares o configuración de entorno

Ejemplo:
```bash
git add .
git commit -m "feat(auth): add email verification on user registration"
```

### 3. Publicar la rama y abrir Pull Request (PR)
```bash
git push -u origin feature/mi-nueva-funcionalidad
```
- Ir a GitHub y abrir un **Pull Request** apuntando hacia la rama **`develop`** (nunca directo a `master`).
- Completar el formulario de la plantilla de PR.
- Solicitar revisión de código antes del merge.
