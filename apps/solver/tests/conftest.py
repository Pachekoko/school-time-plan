import pytest

from solver.contract import SolveRequest

from helpers import con_receso_dict, mat_y_vesp_dict, minimo_dict


@pytest.fixture
def minimo() -> SolveRequest:
    return SolveRequest.model_validate(minimo_dict())


@pytest.fixture
def plantilla_con_receso() -> SolveRequest:
    return SolveRequest.model_validate(con_receso_dict())


@pytest.fixture
def mat_y_vesp() -> SolveRequest:
    return SolveRequest.model_validate(mat_y_vesp_dict())
