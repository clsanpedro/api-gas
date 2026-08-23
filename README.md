# ⛽ Precio Carburante

> Consulta, compara y analiza el precio de los carburantes en las estaciones de
> servicio de España.

**Precio Carburante** es una aplicación web desarrollada en PHP que recopila
información pública sobre estaciones de servicio y precios de carburantes, la
almacena mediante snapshots periódicos y la transforma en información útil para
el usuario.

El proyecto permite consultar precios actuales, navegar geográficamente por
provincias y municipios y, a medida que se acumulan snapshots, analizar la
evolución histórica del precio de cada carburante.

---

## 🚀 Objetivo del proyecto

El precio publicado por una estación es útil.

Su evolución en el tiempo lo es todavía más.

Precio Carburante está diseñado alrededor de esa idea:

```text
Datos públicos
      ↓
Importación
      ↓
Snapshots
      ↓
Base de datos
      ↓
Precios actuales
      +
Histórico
      ↓
Web
```

El objetivo no es únicamente mostrar **cuánto cuesta hoy** un carburante, sino
construir progresivamente un histórico que permita entender **cómo cambia su
precio con el tiempo**.

---

## ✨ Funcionalidades

Actualmente el proyecto incorpora:

- ⛽ Consulta de estaciones de servicio.
- 💶 Precios actuales de carburantes.
- 📈 Histórico de precios basado en snapshots.
- 📊 Gráfico de evolución del precio.
- 🗺️ Localización de estaciones mediante mapa.
- 🔎 Buscador de estaciones, municipios y provincias.
- ⚡ Autocompletado del buscador.
- 🏆 Rankings de estaciones por precio.
- 📍 Navegación por provincia y municipio.
- 📄 Paginación de resultados.
- 🌙 Tema claro y oscuro.
- 📱 Diseño responsive para escritorio y móvil.
- 🔗 URLs amigables.
- 🧭 Breadcrumbs.
- 🗺️ Sitemap dinámico.
- 🤖 `robots.txt`.
- 🔍 URLs canonical.
- 🧩 Datos estructurados para buscadores.
- ↪️ Redirección permanente de slugs incorrectos.
- 🚫 Respuestas HTTP 404 reales para rutas inexistentes.

---

## 📈 Histórico de precios

Una de las piezas principales del proyecto es la conservación de snapshots.

Cada snapshot representa el estado de los precios en un momento determinado. Al
acumularlos podemos construir una serie temporal para cada estación y
carburante.

Por ejemplo:

```text
22/08/2026 · 20:07    1,599 €/l
23/08/2026 · 08:19    1,599 €/l
24/08/2026 · 08:15    1,589 €/l
25/08/2026 · 08:17    1,609 €/l
```

A partir de estos datos la aplicación puede calcular y representar:

- precio actual;
- precio anterior;
- variación absoluta;
- variación porcentual;
- evolución cronológica.

Cuantos más snapshots se acumulen, mayor será el valor histórico de los datos.

---

## 🗺️ Navegación geográfica

Las estaciones pueden explorarse mediante una estructura jerárquica:

```text
España
└── Provincia
    └── Municipio
        └── Estación
```

Ejemplos de rutas:

```text
/gasolineras/
/gasolineras/barcelona/
/gasolineras/barcelona/barcelona/
/gasolinera/15146-by-energy/
```

Esto permite combinar navegación para usuarios, enlazado interno y una
estructura de URLs comprensible para buscadores.

---

## 🔎 Buscador

El buscador permite localizar contenido por distintos criterios, entre ellos:

```text
REPSOL
Barcelona
Mataró
```

Los resultados pueden corresponder a estaciones de servicio, provincias o
municipios.

El sistema incorpora además autocompletado para facilitar la navegación directa
hacia los resultados más relevantes.

---

## 🗺️ Mapas

Las fichas de las estaciones pueden mostrar su posición geográfica mediante
**Leaflet** y cartografía de **OpenStreetMap**.

Esto permite complementar los datos de precio con la ubicación física de la
estación.

---

## 🧱 Arquitectura

La estructura principal del proyecto es:

```text
api-carburantes/
│
├── app/
│   ├── db.php
│   ├── helpers.php
│   ├── history-data.php
│   └── ...
│
├── public/
│   ├── css/
│   │   └── styles.css
│   │
│   ├── js/
│   │   └── app.js
│   │
│   ├── index.php
│   ├── sitemap.php
│   └── ...
│
├── scripts/
│   └── ...
│
├── storage/
│
├── .gitignore
└── README.md
```

### `app/`

Contiene la lógica de aplicación y acceso a datos.

### `public/`

Es la parte pública de la aplicación: front controller, endpoints públicos, CSS,
JavaScript y recursos accesibles desde el navegador.

### `scripts/`

Contiene procesos auxiliares relacionados con la obtención, importación o
mantenimiento de datos.

### `storage/`

Espacio reservado para datos generados localmente que no deben formar parte del
repositorio.

---

## 🛠️ Tecnologías

El proyecto utiliza principalmente:

- **PHP**
- **PDO**
- **MySQL / MariaDB**
- **HTML5**
- **CSS3**
- **JavaScript**
- **Chart.js**
- **Leaflet**
- **OpenStreetMap**
- **Apache / mod_rewrite**

Se ha buscado deliberadamente mantener una arquitectura relativamente sencilla,
evitando dependencias innecesarias.

---

## 🔄 Flujo de datos

De forma simplificada:

```text
Fuente de datos
      │
      ▼
 Scripts de importación
      │
      ▼
   Snapshots
      │
      ├───────────────┐
      ▼               ▼
  Estaciones        Precios
      │               │
      └───────┬───────┘
              ▼
         Aplicación PHP
              │
      ┌───────┴────────┐
      ▼                ▼
 Precio actual     Histórico
      │                │
      └───────┬────────┘
              ▼
          Interfaz web
```

---

## 🔐 Seguridad y configuración

Los archivos que contienen credenciales o información específica del entorno
**no deben almacenarse en el repositorio**.

Por ejemplo:

```text
app/db.php
```

está excluido mediante `.gitignore`.

Cada instalación debe disponer de su propia configuración de conexión a base de
datos.

Nunca deben incluirse en commits:

- contraseñas;
- usuarios de base de datos;
- tokens;
- claves API;
- archivos de configuración privados;
- dumps con información sensible.

---

## 🌐 SEO técnico

El proyecto incorpora una base de SEO técnico orientada a facilitar el rastreo y
la indexación:

```text
sitemap.xml
robots.txt
canonical
breadcrumbs
datos estructurados
URLs amigables
redirecciones 301
respuestas 404 reales
```

El sitemap se genera dinámicamente para poder reflejar el crecimiento del
conjunto de estaciones y páginas geográficas.

---

## 📱 Responsive

La interfaz está diseñada para adaptarse a distintos tamaños de pantalla.

En escritorio se aprovecha el espacio disponible para rankings, tablas y datos
históricos, mientras que en dispositivos pequeños determinados componentes se
transforman en tarjetas para mantener la legibilidad.

---

## 🌙 Tema oscuro

La interfaz dispone de modos claro y oscuro.

La preferencia seleccionada por el usuario se conserva en el navegador para
mantener el tema entre visitas.

---

## 🧪 Estado del proyecto

> **En desarrollo activo**

Actualmente están operativas las piezas principales de navegación, búsqueda,
fichas de estaciones, precios e histórico.

El histórico irá adquiriendo mayor profundidad conforme se acumulen nuevos
snapshots.

---

## 🛣️ Próximos pasos

Entre las posibles líneas de evolución del proyecto se encuentran:

- ampliar el análisis histórico;
- explotar tendencias de precios;
- mejorar rankings y comparativas;
- añadir nuevas herramientas de búsqueda y filtrado;
- seguir optimizando la experiencia móvil;
- mejorar rendimiento y caché;
- ampliar la información útil de estaciones, municipios y provincias;
- continuar el trabajo de indexación y posicionamiento orgánico.

---

## ⚠️ Aviso sobre los datos

Los precios mostrados dependen de la información disponible en la fuente de
datos y del momento en que se haya realizado cada snapshot.

Por tanto, pueden existir diferencias entre el precio almacenado y el precio
existente físicamente en una estación en un momento posterior.

La aplicación debe entenderse como una herramienta informativa y comparativa.

---

## 📄 Licencia

Este proyecto se distribuye bajo la licencia MIT.

Consulta el archivo [LICENSE](LICENSE) para más información.

---

<p align="center">
  <strong>Precio Carburante</strong><br>
  Datos actuales. Histórico. Evolución.
</p>
