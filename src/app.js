const express = require("express");
const path = require("path");
const lawRoutes = require("./routes/lawRoutes");

const app = express();
const publicPath = path.join(__dirname, "public");
const iconsPath = path.join(__dirname, "..", "icons");

app.use(express.json());
app.use(
  express.static(publicPath, {
    etag: false,
    lastModified: false,
    setHeaders: (res) => {
      res.setHeader("Cache-Control", "no-store");
    }
  })
);
app.use(
  "/icons",
  express.static(iconsPath, {
    etag: false,
    lastModified: false,
    setHeaders: (res) => {
      res.setHeader("Cache-Control", "no-store");
    }
  })
);
app.use("/api/laws", lawRoutes);

app.get("/", (req, res) => {
  res.sendFile(path.join(publicPath, "index.html"));
});

module.exports = app;
