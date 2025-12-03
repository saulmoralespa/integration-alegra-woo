# Variables de configuración
WP_TEST__DIR := ../../wp-tests
TEST_UNIT := ${WP_TEST__DIR}/vendor/bin/phpunit
TESTS_DIR := tests

# Colores para output
GREEN := \033[0;32m
YELLOW := \033[1;33m
NC := \033[0m # No Color

.PHONY: test test-calculate-dv test-invoice test-client test-all help

# Ayuda
help:
	@echo "$(YELLOW)Comandos disponibles:$(NC)"
	@echo "  $(GREEN)make test$(NC)              - Ejecutar todos los tests"
	@echo "  $(GREEN)make test-calculate-dv$(NC) - Tests de calculate_dv"
	@echo "  $(GREEN)make test-invoice$(NC)      - Tests de Invoice Generation"
	@echo "  $(GREEN)make test-client$(NC)       - Tests de Client Management"
	@echo "  $(GREEN)make test-all$(NC)          - Todos los tests con detalles"

# Ejecutar todos los tests
test:
	@echo "$(YELLOW)Ejecutando todos los tests...$(NC)"
	WP_TEST__DIR=${WP_TEST__DIR} ${TEST_UNIT} --testdox --colors=always

# Tests de calculate_dv
test-calculate-dv:
	@echo "$(YELLOW)Ejecutando tests de calculate_dv...$(NC)"
	WP_TEST__DIR=${WP_TEST__DIR} ${TEST_UNIT} --filter Test_Integration_Alegra_WC --testdox --colors=always

# Tests de Invoice Generation
test-invoice:
	@echo "$(YELLOW)Ejecutando tests de Invoice Generation...$(NC)"
	WP_TEST__DIR=${WP_TEST__DIR} ${TEST_UNIT} --filter Test_Invoice_Generation --testdox --colors=always

# Tests de Client Management
test-client:
	@echo "$(YELLOW)Ejecutando tests de Client Management...$(NC)"
	WP_TEST__DIR=${WP_TEST__DIR} ${TEST_UNIT} --filter Test_Client_Management --testdox --colors=always

# Todos los tests con detalles
test-all:
	@echo "$(YELLOW)Ejecutando todos los tests con detalles...$(NC)"
	WP_TEST__DIR=${WP_TEST__DIR} ${TEST_UNIT} --colors=always
