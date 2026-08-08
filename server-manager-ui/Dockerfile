# syntax=docker/dockerfile:1

# =============================================================================
# Stage 1 — Build the static bundle
#
# Vite inlines VITE_* variables at BUILD time, so the API URL must be supplied
# as a build arg, not a runtime env var.
# =============================================================================
FROM node:24-alpine AS build

WORKDIR /app

# Install dependencies from the lock file first for a cacheable layer.
COPY package.json package-lock.json ./
RUN npm ci

COPY . .

ARG VITE_API_URL=http://localhost:8000
ARG VITE_APP_NAME="Server Manager"
ENV VITE_API_URL=$VITE_API_URL \
    VITE_APP_NAME=$VITE_APP_NAME

# Runs `tsc -b && vite build`, so a type error fails the image build.
RUN npm run build


# =============================================================================
# Stage 2 — Serve with nginx
# =============================================================================
FROM nginx:1.27-alpine AS runtime

LABEL org.opencontainers.image.title="server-manager-ui" \
      org.opencontainers.image.description="Server Manager React SPA"

RUN rm -rf /usr/share/nginx/html/*

COPY --from=build /app/dist /usr/share/nginx/html
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -q --spider http://127.0.0.1/ || exit 1

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
