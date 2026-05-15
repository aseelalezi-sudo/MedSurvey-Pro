# Step 1: Build TypeScript and Prisma client
FROM node:20-alpine AS builder

WORKDIR /app

# Copy package management files
COPY package*.json ./

# Install all dependencies (including devDependencies for TypeScript compiler and tsx)
RUN npm ci

# Copy the entire backend codebase
COPY . .

# Generate Prisma client based on the local schema
RUN npx prisma generate

# Compile TypeScript codebase to JavaScript in the dist folder
RUN npm run build

# Step 2: Lightweight production runtime
FROM node:20-alpine

WORKDIR /app

# Copy package management files and install only standard production packages
COPY package*.json ./
RUN npm ci --only=production

# Copy compiled files, database schemas, and pre-generated Prisma modules from the builder
COPY --from=builder /app/dist ./dist
COPY --from=builder /app/prisma ./prisma
COPY --from=builder /app/node_modules/.prisma ./node_modules/.prisma
COPY --from=builder /app/node_modules/@prisma/client ./node_modules/@prisma/client

# Expose backend port
EXPOSE 3001

# Set runtime environment
ENV NODE_ENV=production
ENV PORT=3001

# Start the server using compiled JavaScript
CMD ["node", "dist/index.js"]
