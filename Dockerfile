# Step 1: Build the React Vite single-page application
FROM node:20-alpine AS builder

WORKDIR /app

# Copy package configurations
COPY package*.json ./

# Install packages
RUN npm ci

# Copy full application files
COPY . .

# Compile and bundle client application inside dist/
RUN npm run build

# Step 2: Serve compiled static files with Nginx
FROM nginx:alpine

# Remove default Nginx config and replace with SPA-optimized config
RUN rm /etc/nginx/conf.d/default.conf
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Copy built bundle from builder stage to Nginx default public path
COPY --from=builder /app/dist /usr/share/nginx/html

# Expose web service port
EXPOSE 80

# Start high-performance Nginx server
CMD ["nginx", "-g", "daemon off;"]
