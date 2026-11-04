import pytest

from solver.contract import SolveRequest

from helpers import minimo_dict


@pytest.fixture
def minimo() -> SolveRequest:
    return SolveRequest.model_validate(minimo_dict())
