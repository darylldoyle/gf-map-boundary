# 📄 Gravity Forms Map Drawing Plugin – Product Requirements Document (PRD)

---

## 1. TL;DR

This plugin extends Gravity Forms by adding a custom field type that allows users to input their postcode, view a map,
and draw a polygon boundary around a specific location. Upon submission, the drawn shape is saved as a static image (
minimum 1000px wide) and included with the form entry. The plugin also features a settings page to store the Google Maps
API key at a global level. Future versions will support additional map providers such as Mapbox.

---

## 2. Goals

### 🎯 Business Goals

* Enhance the capabilities of Gravity Forms with a geospatial input field
* Provide visual, mappable context to form entries (e.g., land boundaries, delivery zones)
* Offer a flexible plugin architecture to support future enhancements and APIs

### 👤 User Goals

* Easily draw a custom area on a map based on postcode input
* Submit the drawn area with their form entry as a visible image
* View submitted boundary images in both admin and email contexts

### 🚫 Non-Goals

* No storage or processing of geospatial coordinates
* No real-time map validation or geofencing
* No support for mobile drawing gestures (for MVP)

---

## 3. User Stories

| Persona             | User Story                                                                                             |
|---------------------|--------------------------------------------------------------------------------------------------------|
| Form Submitter      | As a user, I want to enter my postcode and draw a boundary on a map so I can define my property area.  |
| Form Submitter      | As a user, I want the ability to undo or edit my boundary if I make a mistake.                         |
| Admin/Form Builder  | As a Gravity Forms admin, I want to add a map drawing field to my form so I can collect boundary data. |
| Admin               | As an admin, I want to set the Google Maps API key in global plugin settings.                          |
| Admin/Form Reviewer | As an admin, I want to see the submitted map image in both the entry detail and email notification.    |

---

## 4. Functional Requirements

### 📌 Core Features

| Feature                      | Description                                                            | Priority |
|------------------------------|------------------------------------------------------------------------|----------|
| Custom GF Field Type         | A new Gravity Forms field type: "Map Drawing"                          | High     |
| Postcode-Based Map Centering | Centers map on user-entered postcode (uses Google Maps geocoding API)  | High     |
| Polygon Drawing Tool         | User can draw lines to create a closed polygon                         | High     |
| Undo/Edit Drawing            | User can undo drawing steps or restart the polygon                     | High     |
| Map Screenshot on Submit     | Static image (min 1000px wide) of map with drawn polygon is generated  | High     |
| Attach Image to Entry        | Image is attached to form entry metadata and admin/email notifications | High     |
| Global API Key Setting       | Admin settings page to store Google Maps API key globally              | High     |
| Mapbox Placeholder           | Architecture allows future addition of Mapbox as a map provider        | Medium   |

---

## 5. User Experience

### 🔄 User Journey

1. User sees a "Draw Area on Map" field in a Gravity Form.
2. User enters their postcode and the map centers on that location.
3. A map appears with a drawing tool enabled.
4. User draws a polygon by clicking points; the shape closes when the first and last points connect.
5. Undo option allows the user to remove the last point or clear the entire shape.
6. Upon submission, the map with the drawn polygon is rendered as a static image.
7. This image appears in the form entry details and email notifications sent to admins.

---

## 6. Narrative

John is applying for a permit using a form on a local council website. He needs to show the area on his property where
construction will take place. Using the "Draw Area on Map" field, he enters his postcode, waits for the map to load, and
then traces the boundary of the proposed construction area using intuitive drawing tools. If he makes a mistake, he
undoes the last point. Once satisfied, he submits the form. The admin receives the form entry with a clear,
high-resolution image showing the exact area John marked.

---

## 7. Technical Considerations

* **Map Rendering**: Google Maps JavaScript API for initial MVP; future-proof structure for Mapbox support
* **Drawing Tool**: Use of Google Maps Drawing Library for polygon creation and event handling
* **Image Capture**: Use `html2canvas` or Google Static Maps API (with polygon overlay) to generate a 1000px-wide image
* **Data Storage**: Only image saved; no need to store polygon coordinates unless explicitly required later
* **Form Field Integration**: Implement as a new Gravity Forms field type using `GF_Field` API
* **Admin Settings Page**: New settings screen under Gravity Forms plugin settings for global API key
* **Security**: Sanitize and validate postcode input; use nonces for form submission
* **Scalability**: Lightweight image generation ensures minimal server load

---

## 8. Success Metrics

| Metric                          | Target Value/Behavior                          |
|---------------------------------|------------------------------------------------|
| Plugin Field Adoption Rate      | >25% of forms using the plugin within 3 months |
| Average Rendering Time          | <2 seconds to load and render map + draw tools |
| Image Generation Reliability    | >99% of submissions include attached image     |
| Admin/API Setting Save Accuracy | 100% reliable storage and retrieval of API key |
| Bug Reports (first 60 days)     | <5 critical bugs affecting form submission     |

---

