module.exports = {
  apps: [
    {
      name: 'medsurvey-pro-backend',
      script: 'dist/index.js',
      instances: 'max', // Utilizes all available CPU cores!
      exec_mode: 'cluster', // Enables Node.js high-performance cluster mode!
      env: {
        NODE_ENV: 'production',
        PORT: 3001,
      },
      max_memory_restart: '1G', // Restarts a cluster worker if memory leaks exceed 1GB
      watch: false, // Do not watch for changes in production to save CPU cycles
      merge_logs: true, // Combines output/error logs from all cluster workers
      error_file: 'logs/pm2-error.log',
      out_file: 'logs/pm2-out.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
    },
  ],
};
